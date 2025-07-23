upda# Security Guide - Plugin Autopsy

This document outlines the security measures implemented in Plugin Autopsy and how to configure them for your environment.

## 🔒 Security Features

### 1. **Access Control**
- **Admin Only**: All functionality requires `manage_options` capability
- **Nonce Verification**: All AJAX requests use WordPress nonces
- **Capability Checks**: Double verification on all sensitive operations

### 2. **Data Sanitization**
- **Query Sanitization**: Removes sensitive data from SQL queries before storage
- **Path Sanitization**: Converts absolute paths to relative paths
- **Input Validation**: All user inputs are sanitized and validated
- **Output Escaping**: All displayed data is properly escaped

### 3. **Privacy Controls**
- **Configurable Logging**: Control what data gets logged
- **Data Retention**: Automatic cleanup of old data
- **Query Length Limits**: Prevent excessive data storage

## ⚙️ Configuration Options

Add these constants to your `wp-config.php` file to control plugin behavior:

### Basic Privacy Controls

```php
// Disable query content logging (keeps count/timing only)
define('PLUGIN_AUTOPSY_LOG_QUERIES', false);

// Disable file path logging 
define('PLUGIN_AUTOPSY_LOG_PATHS', false);

// Disable memory usage tracking
define('PLUGIN_AUTOPSY_LOG_MEMORY', false);

// Disable asset loading tracking
define('PLUGIN_AUTOPSY_LOG_ASSETS', false);
```

### Advanced Configuration

```php
// Limit stored query length (default: 1000 characters)
define('PLUGIN_AUTOPSY_MAX_QUERY_LENGTH', 500);

// Data retention period (default: 30 days)
define('PLUGIN_AUTOPSY_DATA_RETENTION_DAYS', 7);
```

## 🛡️ Security Measures Implemented

### 1. **SQL Injection Prevention**
- All database queries use `$wpdb->prepare()`
- User inputs are sanitized before database operations
- No direct SQL concatenation

### 2. **Sensitive Data Protection**
The plugin automatically removes sensitive information from logged queries:

- Passwords: `password = [REDACTED]`
- API Keys: `api_key = [REDACTED]`
- Tokens: `token = [REDACTED]`
- Secrets: `secret = [REDACTED]`
- Email addresses: `[EMAIL]`
- IP addresses: `[IP]`
- Session data: `session = [REDACTED]`
- Nonces: `nonce = [REDACTED]`

### 3. **File Path Security**
- Absolute server paths are converted to relative paths
- Server directory structure is not exposed
- Path logging can be completely disabled

Example transformation:
```
Before: /var/www/html/wp-content/plugins/my-plugin/file.php
After:  /wp-content/plugins/my-plugin/file.php
```

### 4. **Reflection Security**
- Existence checks before using reflection
- Exception handling for reflection operations
- Error logging without exposing details
- Graceful degradation on failures

### 5. **AJAX Security**
All AJAX endpoints implement:
- Nonce verification
- Capability checks
- Input sanitization
- Error handling
- Rate limiting (WordPress default)

## 🏭 Production Recommendations

### High-Traffic Sites
```php
// Minimal logging for production
define('PLUGIN_AUTOPSY_LOG_QUERIES', false);
define('PLUGIN_AUTOPSY_LOG_PATHS', false);
define('PLUGIN_AUTOPSY_MAX_QUERY_LENGTH', 200);
define('PLUGIN_AUTOPSY_DATA_RETENTION_DAYS', 3);
```

### Development/Staging
```php
// Full logging for development
define('PLUGIN_AUTOPSY_LOG_QUERIES', true);
define('PLUGIN_AUTOPSY_LOG_PATHS', true);
define('PLUGIN_AUTOPSY_MAX_QUERY_LENGTH', 2000);
define('PLUGIN_AUTOPSY_DATA_RETENTION_DAYS', 14);
```

### Security-Conscious Environments
```php
// Maximum security setup
define('PLUGIN_AUTOPSY_LOG_QUERIES', false);
define('PLUGIN_AUTOPSY_LOG_PATHS', false);
define('PLUGIN_AUTOPSY_LOG_MEMORY', true);  // Keep basic metrics only
define('PLUGIN_AUTOPSY_LOG_ASSETS', true);
define('PLUGIN_AUTOPSY_DATA_RETENTION_DAYS', 1);
```

## 🔍 Data Storage

### What Gets Stored
- **Query Metrics**: Count, timing, slow query indicators
- **Memory Usage**: Memory consumption per plugin
- **Asset Information**: File counts and sizes
- **Performance Scores**: Calculated impact ratings

### What Doesn't Get Stored
- **User Credentials**: Never logged or stored
- **Personal Data**: No PII in performance metrics
- **Session Information**: Session data is redacted
- **Full Server Paths**: Only relative paths stored

## 🚨 Security Best Practices

### 1. **Regular Cleanup**
- Use the "Clear Old Data" button regularly
- Set appropriate retention periods
- Monitor database growth

### 2. **Access Control**
- Only give access to trusted administrators
- Regularly review user capabilities
- Use strong passwords for admin accounts

### 3. **Monitoring**
- Review logged data periodically
- Watch for unexpected data growth
- Monitor for suspicious patterns

### 4. **Configuration**
- Start with restrictive settings
- Enable features as needed
- Document your configuration choices

## 🔧 Troubleshooting Security Issues

### Plugin Won't Load
- Check PHP error logs
- Verify file permissions
- Ensure WordPress meets minimum version

### Data Not Appearing
- Check configuration constants
- Verify user capabilities
- Review error logs

### Performance Impact
- Reduce logging options
- Decrease retention period
- Limit query length

## 📞 Security Contact

If you discover a security vulnerability in Plugin Autopsy:

1. **Do not** create a public issue
2. Email security concerns to: [Your Contact]
3. Include detailed reproduction steps
4. Allow time for investigation and patching

## 🔒 Security Changelog

### v1.0.0
- Initial security implementation
- AJAX handler security
- Query sanitization
- Path security
- Reflection protection
- Privacy controls
- Data retention policies