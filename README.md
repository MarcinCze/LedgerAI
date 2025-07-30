# LedgerAI

[![Deploy Transaction API to OVH](https://github.com/MarcinCze/LedgerAI/actions/workflows/deploy-transaction-api.yml/badge.svg)](https://github.com/MarcinCze/LedgerAI/actions/workflows/deploy-transaction-api.yml)

**LedgerAI** is a personal AI-powered finance assistant designed to analyze transactions from multiple banks, normalize them, and provide spending insights through a simple web interface.  
It uses [Semantic Kernel](https://github.com/microsoft/semantic-kernel) for orchestration and [Azure OpenAI Service](https://azure.microsoft.com/en-us/products/cognitive-services/openai-service/) for natural language analysis.

---

## 🚀 Features
- **Multi-bank transaction aggregation** (four banks supported)
- **Data normalization** for consistent structure
- **Secure storage** in MySQL (OVH hosting)
- **CRUD API** in PHP for data access
- **AI analysis** via Semantic Kernel and Azure OpenAI
- **Web chat UI** for natural language queries and insights

---

## 🛠 Architecture
**Architecture Flow:**
1. **React UI** – user sends queries via web interface  
2. **LedgerAI (C# Semantic Kernel REST API)** – handles:
   - Calling PHP CRUD API for transactions  
   - Sending data to Azure OpenAI for analysis  
3. **PHP CRUD API** – secure API for reading/writing transactions in DB  
4. **MySQL (OVH)** – stores normalized transactions  
5. **Azure OpenAI Service** – processes queries and generates insights



---

## 📦 Tech Stack
- **Frontend**: React (chat interface)
- **Backend Agent**: C# Semantic Kernel (REST API)
- **AI**: Azure OpenAI Service (GPT-4o / GPT-4o mini)
- **Database**: MySQL (OVH)
- **Data API**: PHP CRUD API with JWT authentication

---

## 🔒 Security
- JWT authentication between LedgerAI agent and PHP CRUD API
- HTTPS enforced for all API calls
- React app secured via authentication to LedgerAI agent
- Database never exposed publicly

---

## 📌 Status
> **Private personal project.**  
> The source code is visible for reference, but modification, redistribution, or commercial use is prohibited without explicit written consent.

---

## 📜 License
This project is licensed under a **Custom Restrictive License** – see [LICENSE](LICENSE) for details.
