Online Book Haven
Online Book Haven is a PHP and MySQL bookstore portal built for XAMPP. It includes a public landing page, user shopping and borrowing flows, profile management, and role-based workspaces for staff and administrators.

What The Project Does
Lets readers browse books by genre
Supports both buying and borrowing from the same storefront
Tracks orders, order items, and borrowing records
Gives staff tools to manage users and books
Gives admins deeper oversight into accounts, purchases, and borrowings
Lets every role update profile details and profile pictures
Uses SQL views, functions, and stored procedures as a data layer between the UI and database
Main Roles
user

Browse the shop
Add books to buy or borrow carts
Checkout
Update profile, address, password, and profile photo
staff

Access the staff panel
Add, edit, and delete shopper accounts
Add, edit, and delete books
Manage publish dates and catalog details
admin

Everything staff can do
View fuller account activity
Record borrowings and mark returns
See what users bought or borrowed
Core Pages
index.html: public homepage
Lgin.php: login page
Sign.php: sign-up page
shop.php: reader storefront
admin.php: admin/staff dashboard
manage-books.php: catalog management
user-profile.php: profile, address, password, and photo management
contact.html: contact page
Tech Stack
PHP
MySQL
HTML
CSS
JavaScript
Bootstrap 5
XAMPP
Project Structure
Online shop haven/
|-- index.html
|-- shop.php
|-- admin.php
|-- manage-books.php
|-- user-profile.php
|-- user.php
|-- admin-schema.php
|-- data-layer.php
|-- bookstore.sql
|-- css/
|-- js/
|-- img/
Database Design
The main schema is defined in bookstore.sql.

Important tables:

users
addresses
books
orders
orderitems
borrowings
reviews
The project also includes a SQL program layer:

Views

vw_book_catalog_metrics
vw_user_account_overview
Functions

fn_user_total_spent
fn_user_active_loans
fn_user_total_books_borrowed
Stored procedures

sp_get_shop_genres
sp_get_shop_catalog
sp_get_account_directory
sp_get_admin_dashboard_totals
sp_get_book_inventory
sp_get_catalog_summary
sp_get_profile_dashboard
sp_get_profile_recent_purchases
sp_get_profile_recent_borrowings
sp_process_checkout
admin-schema.php also helps older local databases catch up by adding missing columns, tables, views, functions, and procedures at runtime.

Local Setup
1. Put the project in XAMPP
Place the folder inside:

C:\xampp\htdocs\Online shop haven
2. Start XAMPP services
Start:

Apache
MySQL
3. Create or recreate the database
Import bookstore.sql using phpMyAdmin or the MySQL CLI.

Example with MySQL CLI:

& "C:\xampp\mysql\bin\mysql.exe" -u root < "C:\xampp\htdocs\Online shop haven\bookstore.sql"
4. Open the app
Because the folder name contains spaces, the local URL is:

http://localhost/Online%20shop%20haven/index.html
Demo Accounts
After importing bookstore.sql, these seeded accounts are available:

Admin: john@example.com / password123
Staff: staff@example.com / password123
Reader: reader1@example.com / password123
Reader: reader2@example.com / password123
Notable Features
Storefront
Sticky genre and cart tools
Search and sort options
Separate buy and borrow cart modes
Release-date badges and low-stock cues
Checkout that writes real order and borrowing records
Catalog Management
Add books
Edit book details
Quick publish-date updates
Search and filter books
Sort by newest, oldest, stock, and title
Control whether a book can be bought or borrowed
Profile System
Update username, email, phone number, and bio
Upload or remove profile photos
Change password
Save address details
Works for users, staff, and admins
Admin / Staff Operations
Role-aware dashboards
Account directory
Book management
Borrowing records and returns
Purchase and borrowing summaries
Important Implementation Notes
The app uses user.php for authentication helpers and role checks.
The shared database helper layer lives in data-layer.php.
The shared layout and role-aware navigation live in header.php and footer.php.
Runtime-generated uploaded profile images are ignored through .gitignore.
Suggested Next Improvements
Add book cover uploads from the admin/staff UI instead of URL-only entry
Add order history pages for readers
Add review submission and moderation screens
Add pagination for large catalogs
Rename legacy files like Lgin.php and Sign.php to cleaner routes
Add CSRF protection and stricter validation across forms
Repository
