# LedgerAI Transaction API

A PHP-based REST API for managing bank accounts, transactions, and categories. Designed to work with the LedgerAI Semantic Kernel agent for automated transaction processing.

## 🚀 Features

- **CRUD Operations** for accounts, transactions, and categories
- **JWT Authentication** for secure API access
- **Bulk Transaction Import** for Semantic Kernel agent
- **Advanced Filtering** and pagination
- **CORS Support** for React frontend
- **Automatic Deployment** to OVH via GitHub Actions

## 📚 API Endpoints

### Base URL
```
https://your-domain.com/TransactionAPI/api
```

### Authentication
All endpoints (except `/health` and `/auth`) require JWT authentication:
```http
Authorization: Bearer <your-jwt-token>
```

### Health Check
```http
GET /health
```

### Authentication
```http
POST /auth
Content-Type: application/json

{
  "service_name": "semantic_kernel",
  "scope": ["read", "write"]
}
```

## 🏦 Accounts Endpoints

### Get All Accounts
```http
GET /accounts?page=1&limit=50&is_active=true&bank_name=ING&currency=PLN
```

### Get Single Account
```http
GET /accounts/{id}
```

### Create Account
```http
POST /accounts
Content-Type: application/json

{
  "account_iban": "PL18105013571000009091885211",
  "account_number": "1000009091885211",
  "account_name": "Main Account",
  "account_owner": "John Doe",
  "bank_name": "ING Bank",
  "bank_code": "10501357",
  "bank_swift": "INGBPLPW",
  "currency_code": "PLN",
  "current_balance": 1500.00,
  "available_balance": 1400.00,
  "is_active": true,
  "is_monitored": true
}
```

### Update Account
```http
PUT /accounts/{id}
Content-Type: application/json

{
  "current_balance": 1600.00,
  "available_balance": 1500.00,
  "last_statement_date": "2025-01-06"
}
```

### Deactivate Account
```http
DELETE /accounts/{id}
```

## 💰 Transactions Endpoints

### Get Transactions
```http
GET /transactions?account_id=1&date_from=2025-01-01&date_to=2025-01-31&type=DEBIT&category=Food&search=LIDL&page=1&limit=50
```

### Get Single Transaction
```http
GET /transactions/{id}
```

### Create Single Transaction
```http
POST /transactions
Content-Type: application/json

{
  "account_id": 1,
  "value_date": "2025-01-06",
  "booking_date": "2025-01-06",
  "transaction_type": "DEBIT",
  "amount": 25.99,
  "currency_code": "PLN",
  "transaction_code": "073",
  "transaction_title": "Płatność kartą",
  "counterparty_name": "LIDL NAKIELSKA",
  "merchant_name": "LIDL NAKIELSKA",
  "merchant_city": "Tarnowski",
  "card_number_masked": "4246xx1115",
  "category": "Food & Dining",
  "subcategory": "Groceries",
  "confidence_score": 0.95,
  "source_bank": "ING"
}
```

### Bulk Create Transactions (for Semantic Kernel Agent)
```http
POST /transactions
Content-Type: application/json

{
  "account_id": 1,
  "transactions": [
    {
      "value_date": "2025-01-06",
      "booking_date": "2025-01-06",
      "transaction_type": "DEBIT",
      "amount": 25.99,
      "currency_code": "PLN",
      "transaction_title": "Card payment",
      "merchant_name": "LIDL",
      "category": "Food & Dining",
      "source_bank": "ING"
    },
    {
      "value_date": "2025-01-06",
      "booking_date": "2025-01-06",
      "transaction_type": "CREDIT",
      "amount": 800.00,
      "currency_code": "PLN",
      "transaction_title": "Salary",
      "category": "Income",
      "source_bank": "ING"
    }
  ]
}
```

### Update Transaction
```http
PUT /transactions/{id}
Content-Type: application/json

{
  "category": "Shopping",
  "subcategory": "General",
  "confidence_score": 0.88
}
```

### Delete Transaction
```http
DELETE /transactions/{id}
```

## 🏷️ Categories Endpoints

### Get Categories
```http
GET /categories?is_active=true&grouped=true&search=food
```

### Get Single Category
```http
GET /categories/{id}
```

### Create Category
```http
POST /categories
Content-Type: application/json

{
  "category_name": "Food & Dining",
  "subcategory_name": "Fast Food",
  "description": "Quick service restaurants",
  "color_hex": "#FF5722",
  "icon_name": "fastfood"
}
```

### Update Category
```http
PUT /categories/{id}
Content-Type: application/json

{
  "description": "Updated description",
  "color_hex": "#4CAF50"
}
```

### Delete Category
```http
DELETE /categories/{id}
```

## 🔧 Database Setup

1. **Import the schema:**
```sql
mysql -u username -p database_name < TransactionAPI/database/database_schema.sql
```

2. **Set environment variables** (via OVH hosting panel or `.env` file):
```env
DB_HOST=your-mysql-host
DB_NAME=your-database-name
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password
JWT_SECRET=your-secret-key-min-32-chars
```

## 🚀 GitHub Secrets Configuration

Add these secrets to your GitHub repository for automatic deployment:

### Required Secrets (with `TransactionAPI_` prefix):

#### Database Configuration
- **`TRANSACTIONAPI_DB_HOST`** - Your OVH MySQL host
  - Example: `mysql51-farm-x.pro.ovh.net`
- **`TRANSACTIONAPI_DB_NAME`** - Database name
  - Example: `ledgerai_prod`
- **`TRANSACTIONAPI_DB_USERNAME`** - Database username
- **`TRANSACTIONAPI_DB_PASSWORD`** - Database password

#### Security
- **`TRANSACTIONAPI_JWT_SECRET`** - JWT signing secret (min 32 characters)
  - Example: `your-super-secret-jwt-key-with-at-least-32-characters`

#### FTP Deployment
- **`TRANSACTIONAPI_FTP_SERVER`** - OVH FTP server
  - Example: `ftp.your-domain.com`
- **`TRANSACTIONAPI_FTP_USERNAME`** - FTP username
- **`TRANSACTIONAPI_FTP_PASSWORD`** - FTP password
- **`TRANSACTIONAPI_FTP_REMOTE_DIR`** - Remote directory path
  - Example: `/www/api/` or `/public_html/TransactionAPI/`

#### Testing & Monitoring
- **`TRANSACTIONAPI_BASE_URL`** - Full API URL for health checks
  - Example: `https://your-domain.com/TransactionAPI/api`

### How to Add Secrets:
1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add each secret with the exact name from the list above

## 📁 Project Structure

```
TransactionAPI/
├── api/
│   ├── config/
│   │   └── database.php          # Database connection
│   ├── helpers/
│   │   ├── JWT.php              # JWT authentication
│   │   └── Response.php         # Standardized responses
│   ├── endpoints/
│   │   ├── accounts.php         # Account CRUD operations
│   │   ├── transactions.php     # Transaction CRUD + bulk ops
│   │   └── categories.php       # Category management
│   └── index.php               # Main router
├── database/
│   └── database_schema.sql     # MySQL schema
└── README.md                   # This file
```

## 🔒 Security Features

- **JWT Authentication** with token expiration
- **SQL Injection Protection** via prepared statements
- **CORS Headers** configured for cross-origin requests
- **Input Validation** and sanitization
- **Rate Limiting** via server configuration
- **Secure Headers** (XSS protection, content type sniffing prevention)

## 🧪 Testing the API

### Generate a test token:
```bash
curl -X POST https://your-domain.com/TransactionAPI/api/auth \
  -H "Content-Type: application/json" \
  -d '{"service_name": "test", "scope": ["read", "write"]}'
```

### Test endpoints:
```bash
# Health check
curl https://your-domain.com/TransactionAPI/api/health

# Get accounts (with token)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://your-domain.com/TransactionAPI/api/accounts
```

## 🔄 Deployment Process

1. **Push changes** to the `main` branch in the `TransactionAPI/` folder
2. **GitHub Actions** automatically:
   - Validates PHP syntax
   - Tests database schema
   - Creates deployment package
   - Uploads to OVH FTP
   - Tests the deployed API
3. **Check deployment** via health endpoint

## 🏗️ Integration with LedgerAI Architecture

```
┌─────────────────┐    ┌──────────────┐    ┌─────────────┐
│ Semantic Kernel │───▶│ PHP CRUD API │───▶│   MySQL     │
│     Agent       │    │   (OVH)      │    │   (OVH)     │
│                 │    │              │    │             │
│ • MT940 parsing │    │ • JWT auth   │    │ • Normalized│
│ • AI analysis   │    │ • Bulk ops   │    │   data      │
│ • Normalization │    │ • CORS       │    │ • Categories│
└─────────────────┘    └──────────────┘    └─────────────┘
         │                       ▲
         │                       │
         └───────────────────────┘
                React UI
```

## 📊 Example Workflow

1. **Semantic Kernel agent** processes MT940 files
2. **Normalizes transactions** and categorizes with AI
3. **Bulk uploads** to this API via `/transactions` endpoint
4. **React frontend** queries this API for user interface
5. **Real-time insights** from normalized, categorized data

---

**Perfect for your multi-bank transaction aggregation and AI analysis!** 🚀 