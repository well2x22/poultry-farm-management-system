# Poultry Farm Management System

The Poultry Farm Management System is composed of three connected parts:

1. Egg Trading and Grading System - PHP web application
2. API Layer - PHP REST-style API used as the bridge
3. Poultry Inventory System - C# Windows Forms desktop application

The system uses one shared MySQL database named egg_trading_db.

Both the PHP web system and the C# Windows Forms inventory system communicate with the database through the API layer.

==================================================
SYSTEM ARCHITECTURE
==================================================

PHP Egg Trading and Grading System
        ↓
     API Layer
        ↓
  egg_trading_db
        ↑
     API Layer
        ↑
C# Windows Forms Inventory System

The C# Windows Forms application does not connect directly to the database.
It retrieves inventory records through the API layer.

==================================================
1. EGG TRADING AND GRADING SYSTEM
==================================================

The Egg Trading and Grading System is a PHP web application that runs on XAMPP.

Technologies Used:

- PHP
- MySQL
- Bootstrap
- HTML
- CSS
- JavaScript
- cURL
- XAMPP

Main Features:

- Login and registration
- Dashboard summary
- Egg batch management
- Egg grading
- Customer management
- Egg sales management
- Sales deduction from available eggs
- Validation to prevent grading more than the total batch eggs
- API-based data operations

==================================================
2. API LAYER
==================================================

The API layer is a separate PHP folder inside the project.

It is responsible for handling data requests between the systems and the database.

API Folder:

poultry-api

Purpose:

- Receive requests from the PHP web system
- Receive requests from the C# Windows Forms inventory system
- Read and write data in egg_trading_db
- Prevent direct database access from the C# inventory system
- Act as the bridge between the systems

API Files:

poultry-api/db.php
poultry-api/auth.php
poultry-api/add_batch.php
poultry-api/save_grading.php
poultry-api/customers.php
poultry-api/sales.php
poultry-api/egg_inventory.php

==================================================
3. POULTRY INVENTORY SYSTEM
==================================================

The Poultry Inventory System is a C# Windows Forms desktop application.

Technologies Used:

- C#
- Windows Forms
- .NET Framework
- Newtonsoft.Json
- HTTP API requests

Main Features:

- Displays egg inventory records
- Shows total eggs in inventory
- Shows totals by egg size:
  - Extra Large
  - Large
  - Medium
  - Small
- Retrieves data through the API layer
- Does not connect directly to MySQL

==================================================
DATABASE
==================================================

The system uses one shared MySQL database:

egg_trading_db

Main Tables:

users
egg_batches
egg_grades
customers
egg_sales
egg_inventories
api_logs

==================================================
SYSTEM FLOW
==================================================

Egg Grading Flow:

1. Admin logs in to the PHP Egg Trading System.
2. Admin adds an egg batch.
3. Admin grades the eggs by size.
4. The PHP system sends the request to the API layer.
5. The API layer saves the data into egg_trading_db.
6. The inventory records can be viewed in the C# Windows Forms Inventory System.

Inventory Flow:

1. C# Windows Forms Inventory System requests inventory data.
2. The request goes to the API layer.
3. The API layer reads graded egg records from egg_trading_db.
4. The API returns JSON data.
5. The C# Windows Forms app displays the inventory records.

Sales Flow:

1. Admin records a sale in the PHP system.
2. The PHP system sends the sale request to the API layer.
3. The API validates available stock.
4. If valid, the sale is saved.
5. The PHP dashboard totals are updated.

==================================================
IMPORTANT BUSINESS RULES
==================================================

- Graded eggs cannot exceed the total eggs in a batch.
- Sold eggs are deducted from the available eggs in the PHP system.
- The C# Windows Forms system only displays inventory data.
- The C# Windows Forms system does not directly access the database.
- All major data operations pass through the API layer.
- If the API folder is renamed or missing, saving and retrieving data will fail.


This project demonstrates system integration using a PHP web system, a separate PHP API layer, a C# Windows Forms inventory system, and one shared MySQL database.

The API layer acts as the bridge between the systems and ensures that the C# desktop application does not directly connect to the database.