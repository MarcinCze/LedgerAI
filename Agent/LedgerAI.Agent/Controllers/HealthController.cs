using Microsoft.AspNetCore.Mvc;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class HealthController : ControllerBase
    {
        [HttpGet]
        public IActionResult Index()
        {
            return new OkObjectResult(new
            {
                Status = "Healthy",
                Timestamp = DateTime.UtcNow,
                Version = "1.0.0" // You can replace this with your actual version logic
            });
        }
    }
}
