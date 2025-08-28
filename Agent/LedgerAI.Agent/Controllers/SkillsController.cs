using Microsoft.AspNetCore.Mvc;
using Microsoft.SemanticKernel;

namespace LedgerAI.Agent.Controllers
{
    [ApiController]
    [Route("[controller]")]
    public class SkillsController : ControllerBase
    {
        private readonly Kernel kernel;

        public SkillsController(Kernel kernel)
        {
            this.kernel = kernel;
        }

        [HttpGet]
        public IActionResult Index()
        {
            var plugins = kernel.Plugins.Select(p => new
            {
                Name = p.Name,
                Description = p.Description ?? "No description",
                FunctionCount = p.Count(),
                Functions = p.Select(f => new
                {
                    Name = f.Name,
                    Description = f.Description ?? "No description"
                }).ToArray()
            }).ToArray();

            return new OkObjectResult(new
            {
                Plugins = plugins,
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
