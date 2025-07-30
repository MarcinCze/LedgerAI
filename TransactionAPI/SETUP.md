# LedgerAI Transaction API Setup

## 🏗️ New Secure Repository Structure

```
TransactionAPI/
├── api/                    # Private files (above web root)
│   ├── config/            # Database configuration
│   ├── helpers/           # JWT and Response helpers
│   └── endpoints/         # API endpoints
├── public/                # Web-accessible files only
│   ├── index.php         # Main entry point
│   └── .htaccess         # URL rewriting and security
├── database/             # Database schema
└── .env                  # Environment variables (create this!)
```

## 🔧 Local Development Setup

1. **Create environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` with your actual values:**
   ```env
   ENVIRONMENT=development
   
   # Database Configuration
   DB_HOST=localhost
   DB_NAME=ledgerai
   DB_USERNAME=root
   DB_PASSWORD=your-password
   JWT_SECRET=your-super-secret-jwt-key-256-bits-minimum
   
   # Direct User Authentication (Human Administrator)
   DIRECT_USER_USERNAME=admin
   DIRECT_USER_EMAIL=admin@yourdomain.com
   DIRECT_USER_PASSWORD=your_secure_admin_password
   DIRECT_USER_ROLE=admin
   
   # Agent AI Authentication (Semantic Kernel Service)
   AGENT_AI_USERNAME=semantic_kernel
   AGENT_AI_EMAIL=ai@yourdomain.com
   AGENT_AI_PASSWORD=your_secure_service_password
   AGENT_AI_ROLE=user
   
   # API Keys for Service Authentication
   FRONTEND_API_KEY=frontend_api_key_change_this
   SEMANTIC_KERNEL_API_KEY=semantic_kernel_api_key_change_this
   ```

3. **Run database migrations in order:**
   ```sql
   -- First run the main schema
   SOURCE database/database_schema.sql;
   
   -- Then run authentication migration
   SOURCE database/migration_001_authentication.sql;
   ```

4. **Point your web server to the `public/` folder**
   - Apache/Nginx document root should be `TransactionAPI/public/`
   - This ensures private files cannot be accessed directly

## 🚀 Production Deployment

The GitHub Actions workflow now:
1. ✅ **Copies the entire structure** (no complex file moving)
2. ✅ **Creates `.env`** from GitHub secrets automatically  
3. ✅ **Deploys to your FTP** with proper security
4. ✅ **Auto-creates users and API keys** from environment variables

**Note:** You'll need to manually run the database migration on OVH:
- `migration_001_authentication.sql` adds the authentication tables
- Future migrations will be numbered sequentially for easy tracking

### Required GitHub Secrets:
```
# Database
TRANSACTIONAPI_DB_HOST
TRANSACTIONAPI_DB_NAME  
TRANSACTIONAPI_DB_USERNAME
TRANSACTIONAPI_DB_PASSWORD
TRANSACTIONAPI_JWT_SECRET

# Direct User (Human Administrator)
TRANSACTIONAPI_DIRECT_USER_USERNAME
TRANSACTIONAPI_DIRECT_USER_EMAIL
TRANSACTIONAPI_DIRECT_USER_PASSWORD
TRANSACTIONAPI_DIRECT_USER_ROLE

# Agent AI (Semantic Kernel Service)
TRANSACTIONAPI_AGENT_AI_USERNAME
TRANSACTIONAPI_AGENT_AI_EMAIL
TRANSACTIONAPI_AGENT_AI_PASSWORD
TRANSACTIONAPI_AGENT_AI_ROLE

# API Keys
TRANSACTIONAPI_FRONTEND_API_KEY
TRANSACTIONAPI_SEMANTIC_KERNEL_API_KEY

# FTP Deployment
TRANSACTIONAPI_FTP_SERVER
TRANSACTIONAPI_FTP_USERNAME
TRANSACTIONAPI_FTP_PASSWORD
TRANSACTIONAPI_FTP_REMOTE_DIR
```

### OVH Configuration Required:
- **Set your domain document root to `/public`** (not `/`)
- This ensures private files in `/api/` are not web-accessible

## 🔒 Security Benefits

- ✅ **Database credentials** are in `.env` (above web root)
- ✅ **JWT secrets** are in private helpers
- ✅ **Config files** cannot be accessed directly
- ✅ **Only `index.php`** is publicly accessible
- ✅ **`.htaccess` denies** access to sensitive files

## 🧪 Testing

### Health Check (No Auth Required)
```bash
curl https://your-domain.com/health
```

### Authentication (Two-Layer Security)
```bash
curl -X POST https://your-domain.com/auth \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "your_api_key",
    "username": "admin", 
    "password": "your_password",
    "service_name": "test"
  }'
```

### Authenticated Endpoints
```bash
curl https://your-domain.com/categories \
  -H "Authorization: Bearer <your-jwt-token>"
```

## 🔐 New Authentication System

The API now uses **two-layer authentication**:

1. **API Key** (Service Authentication)
   - Controls which applications can request tokens
   - Different keys for frontend, Semantic Kernel, etc.

2. **Username/Password** (User Authentication)
   - **DIRECT-USER**: Human administrator with `admin` role
   - **AGENT-AI**: Semantic Kernel service with `user` role
   - Additional users can be added with `readonly` role

**Benefits:**
- 🛡️ **No anonymous access** - both layers required
- 🔑 **Service isolation** - different API keys per service
- 👥 **User permissions** - role-based access control
- 📊 **Full audit trail** - track usage by service and user