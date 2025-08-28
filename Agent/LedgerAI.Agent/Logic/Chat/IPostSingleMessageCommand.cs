namespace LedgerAI.Agent.Logic.Chat
{
    public interface IPostSingleMessageCommand : ICommand
    {
        Task<(string threadId, string response)> PostSingleMessageAsync(string threadId, string message, Stream fileStream, string fileName);
    }
}
