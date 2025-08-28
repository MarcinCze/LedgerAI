# TransactionAPI Bruno Collection

This Bruno collection contains all the API endpoints for the LedgerAI Transaction API with the "TrxAPI" prefix.

## 🚀 Quick Start

1. **Import the collection** into Bruno
2. **Set up environment variables** in `environments/Development.bru`
3. **Get JWT token** using `TrxAPI-Auth` endpoint
4. **Update the environment** with your actual `baseUrl` and `jwtToken`

## 🔧 Environment Setup

Update `environments/Development.bru` with your actual values:

```json
{
  "baseUrl": "https://your-domain.com/TransactionAPI/api",
  "jwtToken": "your-actual-jwt-token"
}
```

## 📋 Endpoints Overview

### 🔐 Authentication
- **TrxAPI-Auth** - Get JWT token for API access

### 🏥 Health Check
- **TrxAPI-HealthCheck** - Check API health status

### 🏦 Accounts (TrxAPI-Accounts-*)
- **GetAll** - Retrieve all accounts with filtering
- **GetById** - Get specific account by ID
- **Create** - Create new account
- **Update** - Update existing account
- **Delete** - Deactivate/delete account

### 💰 Transactions (TrxAPI-Transactions-*)
- **GetAll** - Retrieve transactions with advanced filtering
- **GetById** - Get specific transaction by ID
- **Create** - Create single transaction
- **CreateBulk** - Bulk create transactions (for Semantic Kernel)
- **Update** - Update transaction details
- **Delete** - Delete transaction

### 🏷️ Categories (TrxAPI-Categories-*)
- **GetAll** - Retrieve all categories
- **GetById** - Get specific category by ID
- **Create** - Create new category
- **Update** - Update category details
- **Delete** - Delete category

## 🔍 Query Parameters

### Transactions Filtering
- `account_id` - Filter by account
- `date_from` / `date_to` - Date range filtering
- `type` - Transaction type (DEBIT/CREDIT)
- `category` - Filter by category
- `search` - Search in titles and names
- `page` / `limit` - Pagination

### Accounts Filtering
- `is_active` - Active accounts only
- `bank_name` - Filter by bank
- `currency` - Filter by currency

## 📝 Request Examples

### Create Transaction
```json
{
  "account_id": 1,
  "value_date": "2025-01-06",
  "transaction_type": "DEBIT",
  "amount": 25.99,
  "currency_code": "PLN",
  "merchant_name": "LIDL",
  "category": "Food & Dining"
}
```

### Bulk Create
```json
{
  "account_id": 1,
  "transactions": [
    {
      "value_date": "2025-01-06",
      "transaction_type": "DEBIT",
      "amount": 25.99,
      "currency_code": "PLN"
    }
  ]
}
```

## 🔒 Authentication

All endpoints (except `/health` and `/auth`) require JWT authentication:

```http
Authorization: Bearer <your-jwt-token>
```

## 🧪 Testing Workflow

1. **Health Check** - Verify API is running
2. **Authentication** - Get JWT token
3. **Update Environment** - Set the token
4. **Test Endpoints** - Use any of the TrxAPI endpoints

## 📚 API Documentation

For detailed API documentation, see the main [TransactionAPI README](../../TransactionAPI/README.md).
