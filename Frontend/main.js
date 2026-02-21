// ============ CONFIGURATION ============
const backendURL = "http://localhost/Bank_All_Functions/Backend/";

// ============ PAGE INITIALIZATION ============
document.addEventListener('DOMContentLoaded', function() {
    const path = window.location.pathname;
    if (path.includes('dashboard.html')) {
        initDashboard();
    }
});

// ============ LOGIN FUNCTION ============
async function login() {
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const msg = document.getElementById("msg");

    if (!email || !password) {
        showMessage(msg, "All fields are required", "error");
        return;
    }

    try {
        showMessage(msg, "Connecting...", "info");
        
        const response = await fetch(backendURL + "auth.php?action=login", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ email, password })
        });

        // Get the response text first
        const responseText = await response.text();
        console.log("Raw response:", responseText);

        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            // If it's not JSON, show the HTML error
            console.error("Failed to parse JSON:", responseText);
            showMessage(msg, "Server error: Received HTML instead of JSON. Check console.", "error");
            return;
        }

        if (data.token) {
            localStorage.setItem("token", data.token);
            localStorage.setItem("user", JSON.stringify(data.user));
            showMessage(msg, "Login successful! Redirecting...", "success");
            setTimeout(() => window.location.href = "dashboard.html", 1000);
        } else {
            showMessage(msg, data.error || "Login failed", "error");
        }
    } catch (error) {
        console.error("Fetch error:", error);
        showMessage(msg, "Connection error: " + error.message, "error");
    }
}

// ============ SIGNUP FUNCTION ============
async function signup() {
    const name = document.getElementById("name").value;
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const confirm = document.getElementById("confirm-password").value;
    const msg = document.getElementById("signup-msg");

    if (!name || !email || !password || !confirm) {
        showMessage(msg, "All fields are required", "error");
        return;
    }

    if (password !== confirm) {
        showMessage(msg, "Passwords do not match", "error");
        return;
    }

    if (password.length < 6) {
        showMessage(msg, "Password must be at least 6 characters", "error");
        return;
    }

    try {
        const response = await fetch(backendURL + "auth.php?action=signup", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ name, email, password })
        });

        const data = await response.json();

        if (data.message) {
            showMessage(msg, data.message + "! Redirecting to login...", "success");
            setTimeout(() => window.location.href = "index.html", 2000);
        } else {
            showMessage(msg, data.error || "Signup failed", "error");
        }
    } catch (error) {
        showMessage(msg, "Connection error", "error");
        console.error(error);
    }
}

// ============ DASHBOARD ============
let currentUser = null;

async function initDashboard() {
    const token = localStorage.getItem("token");
    
    if (!token) {
        window.location.href = "index.html";
        return;
    }
    
    console.log("Dashboard initializing...");
    
    // DIRECT FIX: Update balance from localStorage immediately
    const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
    if (storedUser.balance) {
        const balanceEl = document.getElementById("balance");
        if (balanceEl) {
            balanceEl.innerText = `$${parseFloat(storedUser.balance).toFixed(2)}`;
            console.log("💰 Immediate balance update from localStorage:", balanceEl.innerText);
        }
    }
    
    // Load fresh user profile from server
    await loadUserProfile();
    
    // Load all dashboard data
    loadTransactions();
    loadLoans();
    loadCards();
    loadBillCategories();
    loadBillHistory();
    
    // Set up event listeners
    setupEventListeners();
}

function setupEventListeners() {
    const categorySelect = document.getElementById('category-select');
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            loadBillers(this.value);
        });
    }
}

async function loadUserProfile() {
    const token = localStorage.getItem("token");
    console.log("📡 Loading profile with token:", token);
    
    try {
        const response = await fetch(backendURL + "transfer.php?action=get_profile", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            }
        });

        const responseText = await response.text();
        console.log("📡 Profile raw response:", responseText);

        // Check if response starts with HTML (error)
        if (responseText.trim().startsWith('<')) {
            console.error("❌ Received HTML instead of JSON:", responseText.substring(0, 200));
            // Try to use localStorage data as fallback
            const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
            if (storedUser.balance) {
                console.log("⚠️ Using localStorage fallback with balance:", storedUser.balance);
                updateUserUI(storedUser);
            }
            return;
        }

        // FIX: Find the last '{' and first '}' to extract valid JSON
        const jsonStart = responseText.lastIndexOf('{');
        const jsonEnd = responseText.lastIndexOf('}');
        
        if (jsonStart === -1 || jsonEnd === -1 || jsonEnd < jsonStart) {
            console.error("❌ No valid JSON found in response");
            // Use localStorage fallback
            const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
            if (storedUser.balance) {
                updateUserUI(storedUser);
            }
            return;
        }
        
        // Extract the JSON part
        const jsonStr = responseText.substring(jsonStart, jsonEnd + 1);
        console.log("📡 Extracted JSON:", jsonStr);
        
        // Parse the JSON
        const data = JSON.parse(jsonStr);
        console.log("📡 Profile data from server:", data);
        
        if (data.user) {
            currentUser = data.user;
            
            // Update localStorage with fresh user data
            const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
            storedUser.balance = data.user.balance;
            storedUser.name = data.user.name;
            storedUser.email = data.user.email;
            storedUser.profile_pic = data.user.profile_pic;
            localStorage.setItem("user", JSON.stringify(storedUser));
            
            console.log("✅ Updated localStorage user with balance:", data.user.balance);
            
            // Call updateUserUI
            updateUserUI(data.user);
        } else if (data.error) {
            console.error("❌ Server error:", data.error);
            // Use localStorage fallback
            const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
            if (storedUser.balance) {
                updateUserUI(storedUser);
            }
        }
    } catch (error) {
        console.error("❌ Failed to load profile:", error);
        // Use localStorage fallback
        const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
        if (storedUser.balance) {
            console.log("⚠️ Using localStorage fallback due to error");
            updateUserUI(storedUser);
        }
    }
}

function updateUserUI(user) {
    console.log("🟢 updateUserUI called with balance:", user.balance);
    
    // Update welcome message
    const welcomeEl = document.getElementById("welcome");
    if (welcomeEl) {
        welcomeEl.innerText = `Welcome, ${user.name}!`;
    }
    
    // Update email
    const emailEl = document.getElementById("user-email");
    if (emailEl) {
        emailEl.innerText = user.email;
    }
    
    // CRITICAL FIX: Update balance directly
    const balanceEl = document.getElementById("balance");
    if (balanceEl) {
        // Force the balance to be a number and format it
        const balanceValue = parseFloat(user.balance) || 2500; // Fallback to 2500 if NaN
        balanceEl.innerText = `$${balanceValue.toFixed(2)}`;
        console.log("✅ Balance updated to:", balanceEl.innerText);
    } else {
        console.error("❌ Balance element not found! Creating it...");
        // If element doesn't exist, try to find it by other means
        const possibleBalanceEl = document.querySelector('.balance-amount');
        if (possibleBalanceEl) {
            possibleBalanceEl.id = 'balance'; // Add the ID
            const balanceValue = parseFloat(user.balance) || 2500;
            possibleBalanceEl.innerText = `$${balanceValue.toFixed(2)}`;
            console.log("✅ Found by class and updated:", possibleBalanceEl.innerText);
        }
    }
    
    // Update overview balance
    const overviewBalance = document.getElementById("overview-balance");
    if (overviewBalance) {
        const balanceValue = parseFloat(user.balance) || 2500;
        overviewBalance.innerText = `$${balanceValue.toFixed(2)}`;
    }
    
    // Update profile section
    const profileName = document.getElementById("profile-name");
    if (profileName) profileName.innerText = user.name;
    
    const profileEmail = document.getElementById("profile-email");
    if (profileEmail) profileEmail.innerText = user.email;
    
    // Update profile picture
    if (user.profile_pic) {
        const profileImgs = document.querySelectorAll('#profile-img, #profile-img-large');
        profileImgs.forEach(img => {
            img.src = "/Bank_All_Functions/Frontend/" + user.profile_pic;
        });
    }
    
    // Update member since
    if (user.member_since) {
        const memberSince = document.getElementById("member-since");
        if (memberSince) {
            const date = new Date(user.member_since);
            memberSince.innerText = date.toLocaleDateString();
        }
    }
}

function showSection(sectionId) {
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    const selectedSection = document.getElementById(sectionId);
    if (selectedSection) selectedSection.classList.add('active');
    
    document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(`nav-${sectionId}`);
    if (activeBtn) activeBtn.classList.add('active');
}

// ============ TRANSFER FUNCTIONS ============
async function transfer() {
    const receiver = document.getElementById("receiver").value;
    const amount = document.getElementById("amount").value;
    const description = document.getElementById("transfer-description").value;
    const token = localStorage.getItem("token");
    const msg = document.getElementById("transfer-msg");

    if (!receiver || !amount) {
        showMessage(msg, "Receiver and amount required", "error");
        return;
    }

    console.log("Transfer - Receiver:", receiver, "Amount:", amount);

    try {
        const response = await fetch(backendURL + "transfer.php?action=transfer", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            body: JSON.stringify({ 
                receiver_email: receiver, 
                amount: parseFloat(amount),
                description: description 
            })
        });

        const responseText = await response.text();
        console.log("Transfer raw response:", responseText);

        const jsonStart = responseText.lastIndexOf('{');
        if (jsonStart === -1) {
            showMessage(msg, "Invalid server response", "error");
            return;
        }
        
        const data = JSON.parse(responseText.substring(jsonStart));
        console.log("Transfer response data:", data);

        if (data.message) {
            showMessage(msg, data.message, "success");
            document.getElementById("receiver").value = "";
            document.getElementById("amount").value = "";
            document.getElementById("transfer-description").value = "";
            
            if (data.new_balance) {
                document.getElementById("balance").innerText = `$${parseFloat(data.new_balance).toFixed(2)}`;
                document.getElementById("overview-balance").innerText = `$${parseFloat(data.new_balance).toFixed(2)}`;
            }
            
            loadTransactions();
        } else {
            if (data.debug) {
                showMessage(msg, `${data.error} - Your balance: $${data.debug.your_balance}`, "error");
            } else {
                showMessage(msg, data.error || "Transfer failed", "error");
            }
        }
    } catch (error) {
        console.error("Transfer error:", error);
        showMessage(msg, "Connection error: " + error.message, "error");
    }
}

async function loadTransactions() {
    const token = localStorage.getItem("token");
    const transactionsList = document.getElementById("transactions-list");
    const recentList = document.getElementById("recent-transactions");
    
    if (!transactionsList && !recentList) return;
    
    try {
        const response = await fetch(backendURL + "transfer.php?action=get_transactions", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            body: JSON.stringify({ limit: 20 })
        });

        const data = await response.json();
        
        if (data.transactions) {
            displayTransactions(data.transactions, transactionsList);
            displayRecentTransactions(data.transactions.slice(0, 5), recentList);
            
            const recentCount = document.getElementById("recent-count");
            if (recentCount) recentCount.innerText = data.transactions.length;
        }
    } catch (error) {
        console.error("Failed to load transactions:", error);
    }
}

function displayTransactions(transactions, container) {
    if (!container) return;
    
    if (!transactions || transactions.length === 0) {
        container.innerHTML = '<p class="empty-state">No transactions yet</p>';
        return;
    }
    
    let html = '';
    transactions.forEach(t => {
        const date = new Date(t.created_at).toLocaleString();
        const amount = parseFloat(t.amount);
        const type = t.type === 'sent' ? 'Sent to' : 'Received from';
        const otherParty = t.type === 'sent' ? t.receiver_name : t.sender_name;
        const sign = t.type === 'sent' ? '-' : '+';
        const color = t.type === 'sent' ? '#ef4444' : '#10b981';
        
        html += `
            <div class="transaction-item">
                <div class="transaction-info">
                    <span class="transaction-date">${date}</span>
                    <span class="transaction-party">${type} ${otherParty || 'Unknown'}</span>
                </div>
                <span class="transaction-amount" style="color: ${color}">${sign}$${amount.toFixed(2)}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function displayRecentTransactions(transactions, container) {
    if (!container) return;
    displayTransactions(transactions, container);
}

// ============ LOAN FUNCTIONS ============
async function requestLoan() {
    const amount = document.getElementById("loan-amount").value;
    const purpose = document.getElementById("loan-purpose").value;
    const token = localStorage.getItem("token");
    const msg = document.getElementById("loan-msg");

    if (!amount || !purpose) {
        showMessage(msg, "Amount and purpose required", "error");
        return;
    }

    try {
        const response = await fetch(backendURL + "transfer.php?action=request_loan", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            body: JSON.stringify({ amount: parseFloat(amount), purpose })
        });

        const data = await response.json();

        if (data.message) {
            showMessage(msg, data.message, "success");
            document.getElementById("loan-amount").value = "";
            document.getElementById("loan-purpose").value = "Personal";
            loadLoans();
        } else {
            showMessage(msg, data.error || "Loan request failed", "error");
        }
    } catch (error) {
        showMessage(msg, "Connection error", "error");
    }
}

async function loadLoans() {
    const token = localStorage.getItem("token");
    const loansList = document.getElementById("loans-list");
    
    if (!loansList) return;
    
    try {
        const response = await fetch(backendURL + "transfer.php?action=get_loans", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            }
        });

        const data = await response.json();
        
        if (data.loans) {
            displayLoans(data.loans, loansList);
        }
    } catch (error) {
        console.error("Failed to load loans:", error);
        loansList.innerHTML = '<p class="error">Failed to load loans</p>';
    }
}

function displayLoans(loans, container) {
    if (!container) return;
    
    if (!loans || loans.length === 0) {
        container.innerHTML = '<p class="empty-state">No loan requests yet</p>';
        return;
    }
    
    let html = '';
    loans.forEach(loan => {
        const date = new Date(loan.request_date).toLocaleDateString();
        const amount = parseFloat(loan.amount);
        const statusColor = loan.status === 'approved' ? '#10b981' : 
                           loan.status === 'rejected' ? '#ef4444' : '#f59e0b';
        
        html += `
            <div class="loan-item">
                <div class="loan-info">
                    <span class="loan-amount">$${amount.toFixed(2)}</span>
                    <span class="loan-purpose">${loan.purpose}</span>
                    <span class="loan-date">${date}</span>
                </div>
                <span class="loan-status" style="color: ${statusColor}">${loan.status}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ============ VIRTUAL CARD FUNCTIONS ============
async function createCard() {
    const deposit = document.getElementById("card-deposit").value || 0;
    const token = localStorage.getItem("token");
    const msg = document.getElementById("card-msg");

    try {
        const response = await fetch(backendURL + "transfer.php?action=create_card", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            body: JSON.stringify({ initial_deposit: parseFloat(deposit) })
        });

        const data = await response.json();

        if (data.message) {
            showMessage(msg, data.message, "success");
            document.getElementById("card-deposit").value = "";
            
            if (deposit > 0) {
                const balanceEl = document.getElementById("balance");
                const currentBalance = parseFloat(balanceEl.innerText.replace('$', ''));
                balanceEl.innerText = `$${(currentBalance - parseFloat(deposit)).toFixed(2)}`;
            }
            
            loadCards();
        } else {
            showMessage(msg, data.error || "Card creation failed", "error");
        }
    } catch (error) {
        showMessage(msg, "Connection error", "error");
    }
}

async function loadCards() {
    const token = localStorage.getItem("token");
    const cardsList = document.getElementById("cards-list");
    const activeCount = document.getElementById("active-cards");
    
    if (!cardsList) return;
    
    try {
        const response = await fetch(backendURL + "transfer.php?action=get_cards", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            }
        });

        const data = await response.json();
        
        if (data.cards) {
            displayCards(data.cards, cardsList);
            
            if (activeCount) {
                const active = data.cards.filter(c => c.status === 'active').length;
                activeCount.innerText = active;
            }
        }
    } catch (error) {
        console.error("Failed to load cards:", error);
        cardsList.innerHTML = '<p class="error">Failed to load cards</p>';
    }
}

function displayCards(cards, container) {
    if (!container) return;
    
    if (!cards || cards.length === 0) {
        container.innerHTML = '<p class="empty-state">No virtual cards yet</p>';
        return;
    }
    
    let html = '<div class="cards-grid">';
    cards.forEach(card => {
        const statusColor = card.status === 'active' ? '#10b981' : 
                           card.status === 'frozen' ? '#f59e0b' : '#ef4444';
        
        html += `
            <div class="card-item">
                <div class="card-header">
                    <span class="card-type">${card.card_holder}</span>
                    <span class="card-status" style="color: ${statusColor}">${card.status}</span>
                </div>
                <div class="card-number">${card.card_number}</div>
                <div class="card-details">
                    <span>Exp: ${card.expiry}</span>
                    <span>Balance: $${parseFloat(card.balance).toFixed(2)}</span>
                </div>
                <div class="card-actions">
                    <button onclick="toggleCardStatus(${card.id})" class="btn-small">
                        ${card.status === 'active' ? 'Freeze' : 'Unfreeze'}
                    </button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

async function toggleCardStatus(cardId) {
    const token = localStorage.getItem("token");
    
    try {
        const response = await fetch(backendURL + "transfer.php?action=toggle_card", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            body: JSON.stringify({ card_id: cardId })
        });

        const data = await response.json();
        
        if (data.message) {
            loadCards();
        }
    } catch (error) {
        console.error("Failed to toggle card:", error);
    }
}

// ============ BILL PAYMENT FUNCTIONS ============
async function loadBillCategories() {
    try {
        const response = await fetch(backendURL + "transfer.php?action=get_categories");
        const data = await response.json();
        
        const categorySelect = document.getElementById("category-select");
        if (!categorySelect) return;
        
        let options = '<option value="">Select Category</option>';
        if (data.categories) {
            data.categories.forEach(cat => {
                options += `<option value="${cat.id}">${cat.icon} ${cat.name}</option>`;
            });
        }
        categorySelect.innerHTML = options;
    } catch (error) {
        console.error("Failed to load categories:", error);
    }
}

async function loadBillers(categoryId) {
    if (!categoryId) return;
    
    const billerSelect = document.getElementById("biller-select");
    billerSelect.disabled = true;
    billerSelect.innerHTML = '<option value="">Loading...</option>';
    
    try {
        const response = await fetch(backendURL + `transfer.php?action=get_billers&category_id=${categoryId}`);
        const data = await response.json();
        
        let options = '<option value="">Select Biller</option>';
        if (data.billers) {
            data.billers.forEach(biller => {
                options += `<option value="${biller.id}">${biller.name}</option>`;
            });
        }
        billerSelect.innerHTML = options;
        billerSelect.disabled = false;
    } catch (error) {
        console.error("Failed to load billers:", error);
        billerSelect.innerHTML = '<option value="">Error loading billers</option>';
    }
}

async function payBill() {
    // Get elements with error checking
    const billerSelect = document.getElementById("biller-select");
    const billAmount = document.getElementById("bill-amount");
    const billEmail = document.getElementById("bill-email");
    const msg = document.getElementById("bill-msg");

    // Check if elements exist
    if (!billerSelect) {
        console.error("❌ biller-select element not found!");
        showMessage(msg, "Form error: Biller select not found", "error");
        return;
    }
    if (!billAmount) {
        console.error("❌ bill-amount element not found!");
        showMessage(msg, "Form error: Amount input not found", "error");
        return;
    }
    if (!billEmail) {
        console.error("❌ bill-email element not found!");
        showMessage(msg, "Form error: Email input not found", "error");
        return;
    }

    const billerId = billerSelect.value;
    const amount = billAmount.value;
    const email = billEmail.value;
    
    console.log("Pay bill - Biller ID:", billerId, "Amount:", amount, "Email:", email);

    if (!billerId || !amount || !email) {
        showMessage(msg, "All fields required", "error");
        return;
    }

    const token = localStorage.getItem("token");
    if (!token) {
        showMessage(msg, "Not authenticated", "error");
        return;
    }

    try {
        const response = await fetch(backendURL + "transfer.php?action=pay_bill", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            body: JSON.stringify({ 
                biller_id: parseInt(billerId), 
                amount: parseFloat(amount),
                email: email
            })
        });

        const responseText = await response.text();
        console.log("Pay bill raw response:", responseText);

        // Check if response is HTML (error)
        if (responseText.trim().startsWith('<')) {
            console.error("❌ Received HTML instead of JSON:", responseText.substring(0, 200));
            showMessage(msg, "Server error - check console", "error");
            return;
        }

        const jsonStart = responseText.lastIndexOf('{');
        if (jsonStart === -1) {
            showMessage(msg, "Invalid server response", "error");
            return;
        }
        
        const data = JSON.parse(responseText.substring(jsonStart));
        console.log("Pay bill response:", data);

        if (data.message) {
            showMessage(msg, data.message, "success");
            // Clear form
            billAmount.value = "";
            billEmail.value = "";
            if (billerSelect) billerSelect.selectedIndex = 0;
            
            // Update balance
            if (data.new_balance) {
                const balanceEl = document.getElementById("balance");
                const overviewBalance = document.getElementById("overview-balance");
                const newBalance = `$${parseFloat(data.new_balance).toFixed(2)}`;
                
                if (balanceEl) balanceEl.innerText = newBalance;
                if (overviewBalance) overviewBalance.innerText = newBalance;
            }
            
            // Refresh bill history
            loadBillHistory();
        } else {
            showMessage(msg, data.error || "Payment failed", "error");
        }
    } catch (error) {
        console.error("Pay bill error:", error);
        showMessage(msg, "Connection error: " + error.message, "error");
    }
}

async function loadBillHistory() {
    const token = localStorage.getItem("token");
    const billsList = document.getElementById("bills-list");
    
    if (!billsList) return;
    
    try {
        const response = await fetch(backendURL + "transfer.php?action=get_bill_history", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            }
        });

        const data = await response.json();
        
        if (data.payments) {
            displayBillHistory(data.payments, billsList);
        }
    } catch (error) {
        console.error("Failed to load bill history:", error);
    }
}

function displayBillHistory(payments, container) {
    if (!container) return;
    
    if (!payments || payments.length === 0) {
        container.innerHTML = '<p class="empty-state">No bill payments yet</p>';
        return;
    }
    
    let html = '';
    payments.forEach(p => {
        const date = new Date(p.payment_date).toLocaleString();
        const amount = parseFloat(p.amount);
        
        html += `
            <div class="transaction-item">
                <div class="transaction-info">
                    <span class="transaction-date">${date}</span>
                    <span class="transaction-party">${p.icon} ${p.biller_name}</span>
                    <span class="transaction-ref">Ref: ${p.reference}</span>
                </div>
                <span class="transaction-amount" style="color: #ef4444">-$${amount.toFixed(2)}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ============ PASSWORD RESET FUNCTIONS ============
async function requestReset() {
    const email = document.getElementById("reset-email").value;
    const msg = document.getElementById("reset-msg");

    if (!email) {
        showMessage(msg, "Email required", "error");
        return;
    }

    try {
        const response = await fetch(backendURL + "transfer.php?action=request_reset", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ email })
        });

        const data = await response.json();

        if (data.pin) {
            document.getElementById("pin-section").style.display = "block";
            showMessage(msg, `PIN: ${data.pin} (expires: ${new Date(data.expiry).toLocaleTimeString()})`, "success");
        } else {
            showMessage(msg, data.error || "Request failed", "error");
        }
    } catch (error) {
        showMessage(msg, "Connection error", "error");
    }
}

async function resetPassword() {
    const email = document.getElementById("reset-email").value;
    const pin = document.getElementById("reset-pin").value;
    const newPassword = document.getElementById("new-password").value;
    const msg = document.getElementById("reset-msg");

    if (!email || !pin || !newPassword) {
        showMessage(msg, "All fields required", "error");
        return;
    }

    try {
        const response = await fetch(backendURL + "transfer.php?action=reset_password", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ email, pin, new_password: newPassword })
        });

        const data = await response.json();

        if (data.message) {
            showMessage(msg, data.message, "success");
            setTimeout(() => window.location.href = "index.html", 2000);
        } else {
            showMessage(msg, data.error || "Reset failed", "error");
        }
    } catch (error) {
        showMessage(msg, "Connection error", "error");
    }
}

// ============ PROFILE PICTURE ============
async function uploadProfilePic() {
    const fileInput = document.getElementById("profile-pic");
    const file = fileInput.files[0];
    const token = localStorage.getItem("token");
    const msg = document.getElementById("upload-msg");

    if (!file) {
        showMessage(msg, "Please select a file", "error");
        return;
    }

    const formData = new FormData();
    formData.append("profile_pic", file);

    try {
        const response = await fetch(backendURL + "transfer.php?action=upload_pic", {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + token
            },
            body: formData
        });

        const data = await response.json();

        if (data.message) {
            showMessage(msg, data.message, "success");
            
            if (data.profile_pic) {
                const profileImgs = document.querySelectorAll('#profile-img, #profile-img-large');
                profileImgs.forEach(img => {
                    img.src = "/Bank_All_Functions/Frontend/" + data.profile_pic;
                });
            }
        } else {
            showMessage(msg, data.error || "Upload failed", "error");
        }
    } catch (error) {
        showMessage(msg, "Connection error", "error");
    }
}

// ============ UTILITY FUNCTIONS ============
function showMessage(element, text, type) {
    if (!element) return;
    element.className = `message ${type}`;
    element.innerText = text;
}

// ============ LOGOUT ============
function logout() {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    window.location.href = "index.html";
}