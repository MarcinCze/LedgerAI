namespace LedgerAI.Agent.Models
{
    public class BankStatement
    {
        public string TransactionReference { get; set; }   // :20:
        public string AccountId { get; set; }              // :25:
        public string StatementNumber { get; set; }        // :28C:
        public OpeningBalance OpeningBalance { get; set; } // :60F:
        public List<BankTransaction> Transactions { get; set; } = new();
    }
}
