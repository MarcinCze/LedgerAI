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
   DB_HOST=localhost
   DB_NAME=ledgerai
   DB_USERNAME=root
   DB_PASSWORD=your-password
   JWT_SECRET=your-super-secret-jwt-key-256-bits-minimum
   ```

3. **Point your web server to the `public/` folder**
   - Apache/Nginx document root should be `TransactionAPI/public/`
   - This ensures private files cannot be accessed directly

## 🚀 Production Deployment

The GitHub Actions workflow now:
1. ✅ **Copies the entire structure** (no complex file moving)
2. ✅ **Creates `.env`** from GitHub secrets automatically  
3. ✅ **Deploys to your FTP** with proper security

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

Health check: `GET /health`  
All other endpoints require JWT authentication.