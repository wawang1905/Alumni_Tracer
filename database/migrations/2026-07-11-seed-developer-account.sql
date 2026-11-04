-- Developer account for accessing the developer console from login.
-- Default credentials:
--   Email:    developer@ustp.edu.ph
--   Password: Developer@123

INSERT INTO user_accounts (
    First_Name,
    Last_Name,
    Role,
    Email,
    College,
    Department,
    account_id,
    Password
)
SELECT
    'System',
    'Developer',
    'developer',
    'developer@ustp.edu.ph',
    'USTP',
    'Information Technology',
    NULL,
    'Developer@123'
WHERE NOT EXISTS (
    SELECT 1
    FROM user_accounts
    WHERE Email = 'developer@ustp.edu.ph'
);
