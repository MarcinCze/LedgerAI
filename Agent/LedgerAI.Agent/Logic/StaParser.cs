using LedgerAI.Agent.Models;
using System.Globalization;
using System.Text;
using System.Text.RegularExpressions;

namespace LedgerAI.Agent.Logic
{
    public class StaParser : IStaParser
    {
        private readonly Regex TagLine = new(@"^:(\d{2}[A-Z]?):(.*)$", RegexOptions.Compiled);
        private readonly Regex Tag61 = new(
            // :61: YYMMDD (entry MMDD)? D/C amount[,decimals] (optional letter(s)) remainder
            @"^(?<val>\d{6})(?<entry>\d{4})?(?<dc>[DC])(?<amt>\d+(?:,\d{1,2})?)(?<type>[A-Z])?(?<rest>.*)$",
            RegexOptions.Compiled);

        public BankStatement ParseStatement(string fileContent)
        {
            var stmt = new BankStatement();
            var lines = SplitLines(fileContent);

            BankTransaction currentTx = null;
            var in86 = false;
            var buf86 = new StringBuilder();
            string infoCodeFor86 = null;

            void flush86()
            {
                if (currentTx == null || buf86.Length == 0) return;
                currentTx.Raw86 = buf86.ToString().TrimEnd();
                Parse86Block(currentTx, infoCodeFor86, currentTx.Raw86);
                buf86.Clear();
                infoCodeFor86 = null;
                in86 = false;
            }

            foreach (var raw in lines)
            {
                var line = raw.TrimEnd('\r', '\n');

                // Continuation for :86: lines in Polish MT940 uses ~-prefixed chunks
                if (in86 && line.StartsWith("~"))
                {
                    buf86.AppendLine(line);
                    continue;
                }

                // New tag starts – if we were in :86:, flush collected block
                if (in86 && line.StartsWith(":"))
                {
                    flush86();
                }

                var m = TagLine.Match(line);
                if (!m.Success)
                {
                    // Not a tag line (e.g., stray/empty) - attach to 86 if we're inside
                    if (in86)
                    {
                        buf86.AppendLine(line);
                    }
                    continue;
                }

                var tag = m.Groups[1].Value;
                var payload = m.Groups[2].Value;

                switch (tag)
                {
                    case "20":
                        stmt.TransactionReference = payload.Trim();
                        break;

                    case "25":
                        stmt.AccountId = payload.Trim();
                        break;

                    case "28C":
                        stmt.StatementNumber = payload.Trim();
                        break;

                    case "60F": // Opening balance
                        stmt.OpeningBalance = Parse60(payload);
                        break;

                    case "61":
                        // If we had an unfinished :86: for the previous transaction, flush it.
                        flush86();

                        currentTx = new BankTransaction();
                        stmt.Transactions.Add(currentTx);
                        Parse61(currentTx, payload);
                        break;

                    case "86":
                        // Start of transaction details (can appear multiple lines; Polish variant uses ~ subfields)
                        in86 = true;
                        buf86.Clear();
                        // Often the first :86: line is just an info code like "073"
                        infoCodeFor86 = payload.Trim();
                        // Some files repeat :86: immediately (two :86: lines back-to-back); keep both
                        buf86.AppendLine($":86:{payload}");
                        break;

                    default:
                        // Other tags (e.g., :62F: closing balance) – ignore for now or extend as needed
                        break;
                }
            }

            // End-of-file: flush any pending :86:
            flush86();

            return stmt;
        }

        // --- helpers ---

        private OpeningBalance Parse60(string payload)
        {
            // Example: C250706PLN585,42  => C / 250706 / PLN / 585,42
            if (string.IsNullOrWhiteSpace(payload) || payload.Length < 11) return null;
            
            try
            {
                var dc = payload[0];
                var dateStr = payload.Substring(1, 6);
                var ccy = payload.Substring(7, 3);
                var amtStr = payload.Substring(10);
                
                return new OpeningBalance
                {
                    DebitCredit = dc,
                    Date = ParseYYMMDD(dateStr),
                    Currency = ccy,
                    Amount = ParseAmount(amtStr)
                };
            }
            catch (Exception)
            {
                // Invalid format, return null to skip this entry
                return null;
            }
        }

        private void Parse61(BankTransaction tx, string payload)
        {
            var m = Tag61.Match(payload);
            if (!m.Success) return;

            try
            {
                tx.ValueDate = ParseYYMMDD(m.Groups["val"].Value);
                if (m.Groups["entry"].Success)
                {
                    tx.EntryDate = ParseEntryDate(tx.ValueDate, m.Groups["entry"].Value);
                }
                tx.DebitCredit = m.Groups["dc"].Value[0];
                tx.Amount = ParseAmount(m.Groups["amt"].Value);
                tx.TransactionType = m.Groups["type"].Success ? m.Groups["type"].Value : null;
                tx.Reference = m.Groups["rest"].Value?.Trim();
            }
            catch (Exception ex)
            {
                // Log the error but don't fail the entire parsing
                // Set some basic fallback values
                tx.ValueDate = DateTime.MinValue;
                tx.EntryDate = null;
                tx.DebitCredit = 'D'; // Default to debit
                tx.Amount = 0;
                tx.Reference = $"PARSE_ERROR: {payload} - {ex.Message}";
            }
        }

        private void Parse86Block(BankTransaction tx, string infoCode, string raw86)
        {
            tx.InfoCode = string.IsNullOrWhiteSpace(infoCode) ? null : infoCode.Trim();

            // Polish MT940 often encodes subfields as lines starting with ~NN (two digits)
            // e.g. ~20 (description), ~21 (card number), ~30 (bank code), ~31 (bank reference), ~32 (counterparty), ~33 (city), ~34 (extra)
            // IMPORTANT: Multiple field codes can appear on the same line separated by ~ markers
            var extra = new StringBuilder();
            
            foreach (var rawLine in raw86.Split('\n'))
            {
                var line = rawLine.Trim();
                if (line.StartsWith(":86:")) continue; // first line already captured as InfoCode

                if (line.StartsWith("~"))
                {
                    // Split the line by ~ to handle multiple field codes on same line
                    ParseFieldsInLine(tx, line, extra);
                }
                else if (!string.IsNullOrWhiteSpace(line))
                {
                    // Non-~ content inside 86: keep it in AdditionalInfo for safety
                    extra.AppendLine(line);
                }
            }

            if (extra.Length > 0)
            {
                tx.AdditionalInfo = Append(tx.AdditionalInfo, extra.ToString().Trim());
            }
        }

        private void ParseFieldsInLine(BankTransaction tx, string line, StringBuilder extra)
        {
            // Split by ~ but keep the ~ markers for processing
            var parts = line.Split('~', StringSplitOptions.RemoveEmptyEntries);
            
            foreach (var part in parts)
            {
                if (part.Length >= 2 && char.IsDigit(part[0]) && char.IsDigit(part[1]))
                {
                    var key = part.Substring(0, 2);
                    var val = part.Length > 2 ? part.Substring(2).Trim() : "";

                    switch (key)
                    {
                        case "20": 
                            tx.Description = Append(tx.Description, val); 
                            break;
                        case "21": 
                            // Extract card number from description text
                            var cardNumber = ExtractCardNumber(val);
                            tx.CardNumber = Append(tx.CardNumber, cardNumber); 
                            break;
                        case "30": 
                            tx.BankCode = Append(tx.BankCode, val); 
                            break;
                        case "31": 
                            tx.BankReference = Append(tx.BankReference, val); 
                            break;
                        case "32": 
                            tx.CounterpartyName = Append(tx.CounterpartyName, val); 
                            break;
                        case "33": 
                            tx.CounterpartyAddressOrCity = Append(tx.CounterpartyAddressOrCity, val); 
                            break;
                        case "34": 
                            tx.AdditionalInfo = Append(tx.AdditionalInfo, val); 
                            break;
                        case "38":
                            // IBAN or account number
                            tx.CounterpartyAccount = Append(tx.CounterpartyAccount, val);
                            break;
                        case "62":
                            // Full address with postal code/city
                            tx.CounterpartyFullAddress = Append(tx.CounterpartyFullAddress, val);
                            break;
                        case "22":
                        case "23":
                        case "24":
                        case "25":
                            // These fields often contain additional card/transaction data or are empty
                            if (!string.IsNullOrWhiteSpace(val))
                            {
                                tx.AdditionalInfo = Append(tx.AdditionalInfo, $"~{key}:{val}");
                            }
                            break;
                        case "35":
                        case "36":
                        case "37":
                        case "39":
                            // Additional counterparty/reference fields
                            if (!string.IsNullOrWhiteSpace(val))
                            {
                                tx.AdditionalInfo = Append(tx.AdditionalInfo, $"~{key}:{val}");
                            }
                            break;
                        case "60":
                        case "61":
                        case "63":
                        case "64":
                        case "65":
                            // Additional address/location fields
                            if (!string.IsNullOrWhiteSpace(val))
                            {
                                var currentAddress = tx.CounterpartyFullAddress ?? "";
                                tx.CounterpartyFullAddress = Append(currentAddress, val);
                            }
                            break;
                        default:
                            if (!string.IsNullOrWhiteSpace(val))
                            {
                                extra.AppendLine($"~{key}:{val}");
                            }
                            break;
                    }
                }
                else if (!string.IsNullOrWhiteSpace(part))
                {
                    extra.AppendLine($"~UNPARSED:{part}");
                }
            }
        }

        private string ExtractCardNumber(string cardFieldValue)
        {
            if (string.IsNullOrWhiteSpace(cardFieldValue)) return "";

            // Look for patterns like "Nr karty 4246xx1115" or just "4246xx1115"
            // Extract the actual card number (digits and x characters)
            var cardNumberPattern = new System.Text.RegularExpressions.Regex(@"(\d{4}[x\*]*\d{4})", 
                System.Text.RegularExpressions.RegexOptions.IgnoreCase);
            
            var match = cardNumberPattern.Match(cardFieldValue);
            if (match.Success)
            {
                return match.Groups[1].Value;
            }

            // Fallback: if no clear pattern, return the cleaned value without common prefixes
            var cleaned = cardFieldValue
                .Replace("Nr karty", "")
                .Replace("Karta", "")
                .Replace("Card", "")
                .Trim();
            
            return string.IsNullOrWhiteSpace(cleaned) ? cardFieldValue : cleaned;
        }

        private string Append(string existing, string value)
        {
            if (string.IsNullOrWhiteSpace(value)) return existing;
            if (string.IsNullOrWhiteSpace(existing)) return value;
            return existing + " " + value;
        }

        private DateTime ParseYYMMDD(string yymmdd)
        {
            // Interpret years 00-69 as 2000-2069; 70-99 as 1970-1999 (matches .NET default)
            try
            {
                return DateTime.ParseExact(yymmdd, "yyMMdd", CultureInfo.InvariantCulture);
            }
            catch (Exception ex)
            {
                throw new FormatException($"Invalid date format '{yymmdd}': {ex.Message}", ex);
            }
        }

        private DateTime? ParseEntryDate(DateTime valueDate, string mmdd)
        {
            // Entry date has no year; use valueDate's year
            // Format is MMDD (month-day), not DDMM as originally assumed
            if (string.IsNullOrWhiteSpace(mmdd) || mmdd.Length != 4) return null;
            var month = int.Parse(mmdd.Substring(0, 2), CultureInfo.InvariantCulture);
            var day = int.Parse(mmdd.Substring(2, 2), CultureInfo.InvariantCulture);
            
            // Validate month and day ranges
            if (month < 1 || month > 12) return null;
            if (day < 1 || day > 31) return null;
            
            // Handle year rollovers naively via same year as value date
            var year = valueDate.Year;
            // If entry month is Dec and value is Jan, assume previous year
            if (month == 12 && valueDate.Month == 1) year -= 1;
            // If entry month is Jan and value is Dec, assume next year
            if (month == 1 && valueDate.Month == 12) year += 1;
            
            // Validate the date is representable
            try
            {
                return new DateTime(year, month, day);
            }
            catch (ArgumentOutOfRangeException)
            {
                // Invalid date (e.g., February 30th)
                return null;
            }
        }

        private decimal ParseAmount(string amt)
        {
            // MT940 uses comma as decimal separator regardless of locale
            var normalized = amt.Replace('.', ','); // safety
            return decimal.Parse(normalized, new CultureInfo("pl-PL"));
        }

        private IEnumerable<string> SplitLines(string content)
        {
            using var reader = new System.IO.StringReader(content ?? string.Empty);
            string line;
            while ((line = reader.ReadLine()) != null)
                yield return line;
        }
    }

}
