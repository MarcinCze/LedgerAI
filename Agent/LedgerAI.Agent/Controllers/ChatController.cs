using LedgerAI.Agent.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.SemanticKernel.ChatCompletion;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class ChatController : ControllerBase
    {
        private readonly IChatCompletionService chatService;

        public ChatController(IChatCompletionService chatCompletionService)
        {
            this.chatService = chatCompletionService;
        }

        [HttpPost("messages")]
        public async Task<IActionResult> PostMessage(PostMessageRequest request)
        {
            var result = await chatService.GetChatMessageContentAsync(request.Message);

            return Ok(new PostMessageResponse
            {
                Message = request.Message,
                ThreadId = request.ThreadId,
                Status = "Success",
                Response = result.Content
            });
        }

        [HttpPost("threads")]
        public IActionResult CreateThread()
        {
            return new OkObjectResult(new
            {
                ThreadId = "12345",
                Message = "Thread created successfully",
                Status = "Success"
            });
        }

        [HttpGet("history/{threadId}")]
        public IActionResult GetConversation(string threadId)
        {
            return new OkObjectResult(new
            {
                ThreadId = threadId,
                Messages = new[]
                {
                    new { User = "User1", Text = "Hello!" },
                    new { User = "User2", Text = "Hi there!" }
                },
                Status = "Success"
            });
        }
    }
}
