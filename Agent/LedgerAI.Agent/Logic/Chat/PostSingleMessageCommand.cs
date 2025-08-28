using Microsoft.SemanticKernel;
using Microsoft.SemanticKernel.Connectors.OpenAI;

namespace LedgerAI.Agent.Logic.Chat
{
    public class PostSingleMessageCommand : IPostSingleMessageCommand
    {
        private readonly IEnumerable<string> allowedExtensions = [".txt", ".csv", ".json", ".xml", ".sta"];
        private readonly Kernel semanticKernel;
        private readonly ILogger<PostSingleMessageCommand> logger;
        private readonly IIncomingFileService incomingFileService;

        public PostSingleMessageCommand(
            Kernel semanticKernel, 
            ILogger<PostSingleMessageCommand> logger, 
            IIncomingFileService incomingFileService
            )
        {
            this.semanticKernel = semanticKernel;
            this.logger = logger;
            this.incomingFileService = incomingFileService;
        }

        public async Task<(string threadId, string response)> PostSingleMessageAsync(string threadId, string message, Stream fileStream, string fileName)
        {
            logger.LogInformation("File upload check: HasStream={HasStream}, FileName={FileName}, StreamLength={StreamLength}",
                fileStream != null, fileName ?? "null", fileStream?.Length ?? 0);

            string? filePath = fileStream != null && fileStream.Length > 0 && !string.IsNullOrEmpty(fileName)
                ? await incomingFileService.SaveFileToTempAsync(fileStream, fileName)
                : null;

            var prompt = message;
            if (!string.IsNullOrEmpty(filePath))
            {
                prompt += $"\n\nUser has attached a file named '{filePath}'. If the user wants to analyze this bank statement file, use the analyze_statement function.";
            }

            // Configure function calling settings
            var settings = new OpenAIPromptExecutionSettings
            {
                ToolCallBehavior = ToolCallBehavior.AutoInvokeKernelFunctions
            };

            var kernelArguments = new KernelArguments(settings);
            if (!string.IsNullOrEmpty(filePath))
            {
                kernelArguments["filePath"] = filePath;
            }

            logger.LogInformation("Sending prompt to kernel: {PromptPreview}", 
                prompt.Length > 400 ? string.Concat(prompt.AsSpan(0, 400), "...") : prompt);

            var result = await semanticKernel.InvokePromptAsync(prompt, kernelArguments);

            logger.LogInformation("✅ Kernel response received: {ResponseLength} characters", result.ToString().Length);

            return (threadId, result.ToString());
        }
    }
}
