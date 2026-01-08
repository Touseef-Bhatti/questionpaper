# Google OAuth Setup Guide

## Step 1: Create Google OAuth Application

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to "APIs & Services" > "Credentials"
4. Click "Create Credentials" > "OAuth client ID"
5. If prompted, configure the OAuth consent screen first:
   - Choose "External" for user type
   - Fill in required information (App name, User support email, Developer contact)
   - Add your domain to authorized domains
6. For Application type, select "Web application"
7. Add authorized redirect URIs:
   - `http://localhost/oauth/google-callback.php` (for local development)
   - `https://yourdomain.com/oauth/google-callback.php` (for production)
8. Copy your Client ID and Client Secret

## Step 2: Configure Your Application

### Option 1: Using Environment Variables (.env file)

Create a `.env` file in your `config/` directory:

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

### Option 2: Direct Configuration

Edit `config/google_oauth.php` and replace the default values:

```php
public static function getClientId() 
{
    return 'your-google-client-id.apps.googleusercontent.com';
}

public static function getClientSecret() 
{
    return 'your-google-client-secret';
}
```

## Step 3: Test the Integration

1. Navigate to your login page (`/login.php`)
2. Click the "Continue with Google" button
3. You should be redirected to Google's authentication page
4. After successful authentication, you should be logged in to your application

## Important Notes

- Make sure your database has the required Google OAuth columns (they should be added automatically)
- The redirect URI in your Google Console must exactly match the one in your application
- For production, use HTTPS URLs
- Keep your Client Secret secure and never expose it in client-side code

## Troubleshooting

### Error: "redirect_uri_mismatch"
- Check that the redirect URI in Google Console matches exactly: `https://yourdomain.com/oauth/google-callback.php`

### Error: "OAuth client not found"
- Verify your Client ID is correct
- Make sure the OAuth client hasn't been deleted in Google Console

### Database Errors
- Check that your database connection is working
- Verify the Google OAuth columns were added to the users table

## Security Considerations

- Always use HTTPS in production
- Keep your client secret secure
- Regularly rotate your OAuth credentials
- Monitor failed authentication attempts
