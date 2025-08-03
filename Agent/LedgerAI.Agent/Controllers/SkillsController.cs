using Microsoft.AspNetCore.Mvc;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class SkillsController : ControllerBase
    {
        [HttpGet]
        public IActionResult Index()
        {
            return new OkObjectResult(new
            {
                Skills = new[]
                {
                    new { Name = "Accounting", Description = "Manage financial transactions and reports." },
                    new { Name = "Data Analysis", Description = "Analyze and visualize data trends." },
                    new { Name = "Customer Support", Description = "Assist customers with inquiries and issues." }
                },
                Status = "Success"
            });
        }

        [HttpPost("{skillName}")]
        public IActionResult InvokeSkills(string skillName)
        {
            // Simulate skill invocation logic
            if (string.IsNullOrEmpty(skillName))
            {
                return BadRequest("Skill name cannot be empty.");
            }
            // Here you would typically call the actual skill logic
            return new OkObjectResult(new
            {
                SkillName = skillName,
                Result = "Skill invoked successfully",
                Status = "Success"
            });
        }
    }
}
