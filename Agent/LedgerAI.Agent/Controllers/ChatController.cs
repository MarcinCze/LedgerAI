using Microsoft.AspNetCore.Mvc;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class ChatController : ControllerBase
    {
        [HttpPost("messages")]
        public IActionResult PostMessage()
        {
            return new OkObjectResult(new
            {
                Message = "Chat messages received successfully",
                Status = "Success"
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
