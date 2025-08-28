namespace LedgerAI.Agent.Models
{
    public class BankTransaction
    {
        // From :61:
        public DateTime ValueDate { get; set; }
        public DateTime? EntryDate { get; set; }
        public char DebitCredit { get; set; } // D/C
        public decimal Amount { get; set; }
        public string TransactionType { get; set; } // optional letter(s) after amount
        public string Reference { get; set; }       // rest of :61: (e.g., S0739730...)
                                                    // From :86: (Polish subfields with '~' continuation)
        public string Raw86 { get; set; }                   // entire 86 block (debugging)
        public string InfoCode { get; set; }                // first :86: line (e.g., 073)
        public string BankCode { get; set; }                // ~30
        public string BankReference { get; set; }           // ~31
        public string Description { get; set; }             // ~20 (e.g., "Płatność kartą...")
        public string CardNumber { get; set; }              // ~21 (masked)
        public string CounterpartyName { get; set; }        // ~32
        public string CounterpartyAddressOrCity { get; set; } // ~33
        public string CounterpartyAccount { get; set; }     // ~38 (IBAN/account number)
        public string CounterpartyFullAddress { get; set; } // ~62 (full address with postal code)
        public string AdditionalInfo { get; set; }          // ~34 (and anything unrecognized)
    }
}
