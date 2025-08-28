using LedgerAI.Agent.Logic.Chat;
using LedgerAI.Agent.Models;
using Microsoft.AspNetCore.Mvc;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class ChatController : ControllerBase
    {
        private readonly IPostSingleMessageCommand postSingleMessageCommand;

        public ChatController(
            IPostSingleMessageCommand postSingleMessageCommand
            )
        {
            this.postSingleMessageCommand = postSingleMessageCommand;
        }

        [HttpPost("messages")]
        public async Task<IActionResult> PostMessage([FromForm] PostMessageRequest request)
        {
            using var stream = request.File?.OpenReadStream();
            var (threadId, response) = await this.postSingleMessageCommand.PostSingleMessageAsync(
                request.ThreadId, 
                request.Message, 
                stream, 
                request.File?.FileName
                );
            
            return Ok(new PostMessageResponse
            {
                Message = request.Message,
                ThreadId = threadId,
                Status = "Success",
                Response = response
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
