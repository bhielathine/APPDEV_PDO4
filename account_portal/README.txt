ACCOUNT RECORD KEEPING / SELLING PORTAL  (PHP + MySQL)

SETUP (XAMPP)
1. Copy this folder to C:\xampp\htdocs\account_portal
2. Start Apache and MySQL in the XAMPP control panel.
3. Open http://localhost/phpmyadmin, click Import, choose database/schema.sql, click Go.
4. Open config/config.php:
   - keep BASE_URL as /account_portal (match the folder name)
   - change APP_KEY to your own long random string
5. Visit http://localhost/account_portal/ and register two users
   (one to sell, one to buy) to try the whole flow.

ONE-TO-MANY RELATIONSHIPS
  users (PARENT)  1 --- *  accounts (CHILD)   one seller, many account records
  games           1 --- *  accounts           one game, many account records
  accounts        1 --- *  offers             one listing, many offers
  users           1 --- *  offers             one buyer, many offers

STATUS FLOW OF AN ACCOUNT RECORD
  draft --Publish--> available --Mark sold (needs a pending offer)--> sold
  available --Unpublish--> draft        sold --Mark available--> available
