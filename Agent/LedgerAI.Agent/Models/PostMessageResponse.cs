namespace LedgerAI.Agent.Models
{
    public class PostMessageResponse
    {
        public string? ThreadId { get; init; }
        public required string Message { get; init; }
        public required string Status { get; init; }
        public string? Response { get; init; }
    }
}
