namespace LedgerAI.Agent.Models
{
    /// <summary>
    /// MT940 Transaction Entry Types
    /// </summary>
    public enum TransactionEntryType
    {
        /// <summary>
        /// Unknown or unrecognized transaction type
        /// </summary>
        Unknown = 0,

        /// <summary>
        /// Standard Entry - Normal bank statement entry (most common)
        /// </summary>
        Standard = 'S',

        /// <summary>
        /// Final Entry - Final or closing entry
        /// </summary>
        Final = 'F',

        /// <summary>
        /// Reversal - Transaction reversal or cancellation
        /// </summary>
        Reversal = 'R',

        /// <summary>
        /// Credit Adjustment - Manual credit adjustment
        /// </summary>
        CreditAdjustment = 'C',

        /// <summary>
        /// Debit Adjustment - Manual debit adjustment
        /// </summary>
        DebitAdjustment = 'D',

        /// <summary>
        /// Provisional Entry - Temporary or pending entry
        /// </summary>
        Provisional = 'P',

        /// <summary>
        /// Exchange Rate Entry - Foreign exchange related
        /// </summary>
        ExchangeRate = 'E',

        /// <summary>
        /// Information Entry - Informational only (no monetary impact)
        /// </summary>
        Information = 'I'
    }
}
