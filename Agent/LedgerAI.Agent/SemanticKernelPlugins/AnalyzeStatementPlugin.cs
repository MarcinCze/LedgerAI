using LedgerAI.Agent.Logic;
using LedgerAI.Agent.Models;
using Microsoft.SemanticKernel;
using System.ComponentModel;

namespace LedgerAI.Agent.SemanticKernelPlugins
{
    public class AnalyzeStatementPlugin
    {
        private readonly ILogger<AnalyzeStatementPlugin> logger;
        private readonly IIncomingFileService incomingFileService;
        private readonly IStaParser staParser;

        public AnalyzeStatementPlugin(
            ILogger<AnalyzeStatementPlugin> logger, 
            IIncomingFileService incomingFileService, 
            IStaParser staParser
            )
        {
            this.logger = logger;
            this.incomingFileService = incomingFileService;
            this.staParser = staParser;
        }

        [KernelFunction("analyze_statement")]
        [Description("Analyze a bank statement file and import transactions. Call this when user wants to analyze/process/import bank statement data.")]
        public async Task<string> AnalyzeStatementAsync(
        [Description("The file path with file name and extension to the saved bank statement file")] string filePath)
        {
            try
            {
                string fileContent = await incomingFileService.ReadFileAsync(filePath);
                BankStatement? statement = ParseStatement(fileContent);

                return $"Imported.";
            }
            catch (Exception ex)
            {
                logger.LogError(ex, $" Failed to analyze statement {filePath}");
                return $"Failed to analyze {filePath}: {ex.Message}";
            }
        }

        private BankStatement? ParseStatement(string fileContent)
        {
            try
            {
                return staParser.ParseStatement(fileContent);
            }
            catch (Exception ex)
            {
                logger.LogError($"Failed to parse statement content. Exception: {ex.Message}");
                return null;
            }
        }
    }
}
