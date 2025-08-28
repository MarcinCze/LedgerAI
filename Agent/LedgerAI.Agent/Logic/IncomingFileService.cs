namespace LedgerAI.Agent.Logic
{
    public class IncomingFileService : IIncomingFileService
    {
        private const string TempDirectory = "TempUploads";

        public IncomingFileService()
        {
            if (!Directory.Exists(TempDirectory))
            {
                Directory.CreateDirectory(TempDirectory);
            }
        }

        public async Task<string> SaveFileToTempAsync(Stream fileStream, string fileName)
        {
            var tempFilePath = Path.Combine(TempDirectory, $"{Guid.NewGuid()}_{fileName}");
            using (var fileOutput = new FileStream(tempFilePath, FileMode.Create, FileAccess.Write))
            {
                await fileStream.CopyToAsync(fileOutput);
            }
            return tempFilePath;
        }

        public async Task<string> ReadFileAsync(string filePath)
        {
            // Try to detect encoding and read the file properly
            return await ReadFileWithEncodingDetectionAsync(filePath);
        }

        private async Task<string> ReadFileWithEncodingDetectionAsync(string filePath)
        {
            // Register encoding providers for Windows codepages
            System.Text.Encoding.RegisterProvider(System.Text.CodePagesEncodingProvider.Instance);
            
            // Read a small sample to detect encoding
            byte[] buffer = new byte[1024];
            using (var fileStream = File.OpenRead(filePath))
            {
                await fileStream.ReadAsync(buffer, 0, buffer.Length);
            }

            // Try different encodings commonly used for Polish banking files
            // Ordered by likelihood of success based on testing
            var encodingsToTry = new[]
            {
                System.Text.Encoding.GetEncoding(852),     // CP852 - DOS Central European (best for Polish STA files)
                System.Text.Encoding.GetEncoding(1250),    // Windows-1250 - Central European
                System.Text.Encoding.UTF8,                 // UTF-8 (fallback)
                System.Text.Encoding.GetEncoding(28592),   // ISO-8859-2 - Latin-2 Central European  
                System.Text.Encoding.GetEncoding(1252)     // Windows-1252 - Western European
            };

            string? bestResult = null;
            var bestScore = 0;

            foreach (var encoding in encodingsToTry)
            {
                try
                {
                    using var reader = new StreamReader(filePath, encoding);
                    var content = await reader.ReadToEndAsync();
                    
                    // Score based on the presence of valid Polish characters and absence of replacement chars
                    var score = ScoreEncodingQuality(content);
                    
                    if (score > bestScore)
                    {
                        bestScore = score;
                        bestResult = content;
                    }
                }
                catch
                {
                    // Encoding failed, try next one
                    continue;
                }
            }

            return bestResult ?? throw new InvalidOperationException($"Could not read file {filePath} with any supported encoding");
        }

        private int ScoreEncodingQuality(string content)
        {
            var score = 0;
            
            // Positive points for valid Polish characters
            if (content.Contains('ł')) score += 10;
            if (content.Contains('ą')) score += 10;
            if (content.Contains('ę')) score += 10;
            if (content.Contains('ś')) score += 10;
            if (content.Contains('ć')) score += 10;
            if (content.Contains('ń')) score += 10;
            if (content.Contains('ó')) score += 10;
            if (content.Contains('ź')) score += 10;
            if (content.Contains('ż')) score += 10;
            
            // Negative points for replacement characters (encoding errors)
            score -= content.Count(c => c == '�') * 5;
            
            // Positive points for common Polish banking terms
            if (content.Contains("płatność", StringComparison.OrdinalIgnoreCase)) score += 5;
            if (content.Contains("przelew", StringComparison.OrdinalIgnoreCase)) score += 5;
            if (content.Contains("kartą", StringComparison.OrdinalIgnoreCase)) score += 5;
            
            return score;
        }
    }
}
