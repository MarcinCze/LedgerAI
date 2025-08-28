using LedgerAI.Agent.Models;
using System.Diagnostics;
using System.Text;

namespace LedgerAI.Agent.Logic
{
    public interface IStaParser
    {
        BankStatement ParseStatement(string fileContent);
    }
}
