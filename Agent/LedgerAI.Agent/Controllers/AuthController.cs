using Microsoft.AspNetCore.Mvc;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class AuthController : ControllerBase
    {
        [HttpPost("login")]
        public IActionResult Login()
        {
            return new OkObjectResult(new
            {
                Message = "Login successful",
                Token = "sample-jwt"
            });
        }

        [HttpPost("refresh")]
        public IActionResult Refresh()
        {
            return new OkObjectResult(new
            {
                Message = "Token refreshed",
                Token = "new-sample"
            });
        }
    }
}
