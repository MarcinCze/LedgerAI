namespace LedgerAI.Agent.Models
{
    public class PostMessageRequest
    {
        public required string Message { get; set; }
        public string? ThreadId { get; set; }
        public IFormFile? File { get; set; }
    }
}
