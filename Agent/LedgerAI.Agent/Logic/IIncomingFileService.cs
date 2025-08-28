namespace LedgerAI.Agent.Logic
{
    public interface IIncomingFileService
    {
        Task<string> SaveFileToTempAsync(Stream fileStream, string fileName);

        Task<string> ReadFileAsync(string filePath);
    }
}
