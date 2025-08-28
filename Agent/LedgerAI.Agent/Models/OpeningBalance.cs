namespace LedgerAI.Agent.Models
{
    public class OpeningBalance
    {
        public DateTime Date { get; set; }
        public string Currency { get; set; }
        public decimal Amount { get; set; }
        public char DebitCredit { get; set; } // C/D
    }
}
