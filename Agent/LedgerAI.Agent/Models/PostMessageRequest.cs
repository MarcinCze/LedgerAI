namespace LedgerAI.Agent.Models
{
    public class PostMessageRequest
    {
        public string? ThreadId { get; init; }
        public required string Message { get; init; }
    }
}
