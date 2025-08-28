namespace LedgerAI.Agent.Models
{
    /// <summary>
    /// Debit/Credit indicator for bank transactions
    /// </summary>
    public enum DebitCreditType
    {
        /// <summary>
        /// Unknown or unrecognized type
        /// </summary>
        Unknown = 0,

        /// <summary>
        /// Debit - Money going OUT of the account (expenses, payments, withdrawals)
        /// </summary>
        Debit = 'D',

        /// <summary>
        /// Credit - Money coming IN to the account (income, deposits, refunds)
        /// </summary>
        Credit = 'C'
    }
}
