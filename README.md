# 🏦 **BankApp - Full Stack Banking Application**

<div align="center">

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)
![JWT](https://img.shields.io/badge/JWT-Auth-red)
![License](https://img.shields.io/badge/license-MIT-green)

**A fully functional banking application with authentication, transfers, loans, virtual cards, bill payments and more!**

[✨ Features](#-features) • 
[🚀 Quick Start](#-quick-start) • 
[📸 Screenshots](#-screenshots) • 
[🛠️ Tech Stack](#️-tech-stack) • 
[📖 Documentation](#-documentation)

</div>

---

## ✨ **Features**

### 🔐 **Authentication & Security**
- ✅ User registration with email validation
- ✅ Secure login with JWT tokens
- ✅ Password reset with 3-digit PIN
- ✅ Session management with localStorage

### 💰 **Core Banking**
- ✅ Account balance management (starting at $2500)
- ✅ Money transfers between users
- ✅ Real-time balance updates
- ✅ Transaction history with filters

### 📱 **Advanced Features**
- ✅ **Loan Requests** - Apply for personal/business loans
- ✅ **Virtual Cards** - Create and manage digital cards
- ✅ **Bill Payments** - Pay utilities with email
- ✅ **Profile Management** - Upload profile pictures
- ✅ **Transaction History** - View all activities

### 🎨 **User Experience**
- ✅ Modern, responsive design
- ✅ Smooth animations and transitions
- ✅ Mobile-friendly interface
- ✅ Real-time notifications
- ✅ Error handling with user-friendly messages

---

## 🚀 **Quick Start**

### 📋 **Prerequisites**
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Composer
- XAMPP/WAMP/MAMP (for local development)

### ⚙️ **Installation**

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/bank-app.git
cd bank-app
```

2. **Install dependencies**
```bash
cd Backend
composer install
```

3. **Configure the database**
- Create a MySQL database named `bank_app`
- Update database credentials in `Backend/config.php`
```php
$host = "localhost";
$user = "root";
$pass = "";
```

4. **Set up the project**
```bash
# Create uploads directory
mkdir -p Frontend/uploads
chmod 777 Frontend/uploads

# Add default profile picture
# Place a default.png in Frontend/uploads/
```

5. **Run the application**
- Start Apache and MySQL
- Visit `http://localhost/Bank_All_Functions/Frontend/index.html`

### 🎯 **Default Test Account**
```
Email: test@example.com
Password: password123
```

---

## 📸 **Screenshots**

<div align="center">
  
### 🔐 **Login Page**

![Screenshot 2026-02-21 135720](https://github.com/user-attachments/assets/ae466c84-e449-45ae-9ea7-e10c3b520b9a)





### 📊 **Dashboard Overview**

![Screenshot 2026-02-21 135825](https://github.com/user-attachments/assets/06907f75-74b0-482a-b049-0d7fea9621e5)




### 💳 **Virtual Cards**

![Screenshot 2026-02-21 140034](https://github.com/user-attachments/assets/c4278d9d-e035-4c6b-91ea-885b23d3484e)




</div>

---

## 🛠️ **Tech Stack**

### **Frontend**
- ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
- ![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
- ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)

### **Backend**
- ![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
- ![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
- ![JWT](https://img.shields.io/badge/JWT-000000?style=flat&logo=json-web-tokens&logoColor=white)

### **Libraries**
- Firebase JWT for authentication
- Font Awesome for icons
- Google Fonts (Inter, Plus Jakarta Sans)

---

## 📁 **Project Structure**

```
Bank_All_Functions/
├── 📁 Frontend/
│   ├── 📄 index.html          # Login/Signup/Forgot Password
│   ├── 📄 dashboard.html       # Main dashboard
│   ├── 📄 main.js              # All JavaScript logic
│   ├── 📄 style.css            # Styling with animations
│   └── 📁 uploads/             # Profile pictures
│       └── default.png
└── 📁 Backend/
    ├── 📄 config.php           # Database connection
    ├── 📄 auth.php             # Authentication endpoints
    ├── 📄 transfer.php          # All other functions
    └── 📁 vendor/               # Composer dependencies
```

---

## 🎯 **API Endpoints**

### **Authentication**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `auth.php?action=login` | POST | User login |
| `auth.php?action=signup` | POST | User registration |
| `auth.php?action=verify` | POST | Verify JWT token |

### **Banking Operations**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `transfer.php?action=transfer` | POST | Send money |
| `transfer.php?action=get_transactions` | POST | View history |
| `transfer.php?action=request_loan` | POST | Apply for loan |
| `transfer.php?action=get_loans` | POST | View loans |
| `transfer.php?action=create_card` | POST | Create virtual card |
| `transfer.php?action=get_cards` | POST | View cards |
| `transfer.php?action=pay_bill` | POST | Pay bills |
| `transfer.php?action=upload_pic` | POST | Upload profile pic |

---

## 🎨 **Features Demo**

### **User Authentication**
```
📝 Register → 🔐 Login → 🎫 Get JWT → 🏦 Access Dashboard
```

### **Money Transfer**
```
💰 Check Balance → 📧 Enter Receiver Email → 💸 Enter Amount → ✅ Confirm Transfer
```

### **Virtual Card Creation**
```
💳 Click Create Card → 💰 Add Initial Deposit → 🎉 Get Card Details → 🔄 Manage Card
```

### **Bill Payment**
```
📋 Select Category → 🏢 Choose Biller → 📧 Enter Email → 💵 Pay Amount
```

---

## 🔧 **Troubleshooting**

### **Common Issues**

| Issue | Solution |
|-------|----------|
| Connection error | Check if Apache/MySQL is running |
| 404 Not Found | Verify project path in main.js |
| JWT errors | Run `composer install` in Backend |
| Upload fails | Check permissions on uploads folder |
| Balance not updating | Clear browser localStorage |

### **Debug Mode**
```javascript
// Add to browser console to debug
localStorage.clear();
console.log('Storage cleared. Try logging in again.');
```

---

## 🤝 **Contributing**

Contributions are welcome! Here's how you can help:

1. 🍴 Fork the repository
2. 🌿 Create a feature branch
3. 💻 Make your changes
4. ✅ Test thoroughly
5. 📤 Submit a pull request

---

## 🙏 **Acknowledgments**

- Firebase PHP-JWT library for authentication
- Font Awesome for beautiful icons
- Google Fonts for typography
- All contributors and testers

---

<div align="center">

### ⭐ **If you like this project, don't forget to give it a star!** ⭐

</div>

---

<div align="center">
  
  Made with ❤️ 

</div>
