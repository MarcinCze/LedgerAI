using LedgerAI.Agent.Logic;
using LedgerAI.Agent.SemanticKernelPlugins;
using Microsoft.SemanticKernel;
using System.Reflection;

namespace LedgerAI.Agent
{
    public static class Dependencies
    {
        public static IServiceCollection AddCustomDependencies(this IServiceCollection services)
        {
            services
                .AddSingleton<IIncomingFileService, IncomingFileService>()
                .AddSingleton<IStaParser, StaParser>();
            return services;
        }

        public static IServiceCollection AddApplicationDependencies(this IServiceCollection services)
        {
            var assembly = Assembly.GetExecutingAssembly();
            var baseInterface = typeof(ICommand);

            var commandInterfaces = assembly.GetTypes()
                .Where(t => t.IsInterface
                         && baseInterface.IsAssignableFrom(t)
                         && t != baseInterface);

            foreach (var commandInterface in commandInterfaces)
            {
                var implementation = assembly.GetTypes()
                    .FirstOrDefault(t => t.IsClass
                                      && !t.IsAbstract
                                      && commandInterface.IsAssignableFrom(t));

                if (implementation != null)
                {
                    services.AddSingleton(commandInterface, implementation);
                }
            }

            return services;
        }

        public static IServiceCollection AddSemanticKernelDependencies(this IServiceCollection services, IConfiguration configuration)
        {
            services.AddSingleton<AnalyzeStatementPlugin>();

            services.AddSingleton<Kernel>(serviceProvider =>
            {
                var kernelBuilder = Kernel.CreateBuilder();

                kernelBuilder.AddAzureOpenAIChatCompletion(
                    deploymentName: configuration["AzureOpenAI:Deployment"],
                    endpoint: configuration["AzureOpenAI:Endpoint"],
                    apiKey: configuration["AzureOpenAI:ApiKey"]
                );

                var analyzePlugin = serviceProvider.GetRequiredService<AnalyzeStatementPlugin>();
                kernelBuilder.Plugins.AddFromObject(analyzePlugin, "AnalyzeStatementPlugin");

                var kernel = kernelBuilder.Build();

                return kernel;
            });

            return services;
        }
    }
}
