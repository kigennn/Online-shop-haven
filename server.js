const express = require("express");
const mysql = require("mysql2");
const cors = require("cors");

const app = express();
app.use(cors());
app.use(express.json());

// MySQL Database Connection
const db = mysql.createConnection({
    host: "127.0.0.1",
    user: "root",
    password: "", // Set your MySQL password
    database: "bookstore"
});

db.connect(err => {
    if (err) {
        console.error("Database connection error:", err);
        return;
    }
    console.log("Connected to MySQL database");
});

// Search API with Category Filter
app.get("/search", (req, res) => {
    const query = req.query.q || "";
    const category = req.query.category || "";
    
    let sql = "SELECT * FROM books WHERE title LIKE ?";
    let params = [`%${query}%`];

    if (category) {
        sql += " AND category = ?";
        params.push(category);
    }

    db.query(sql, params, (err, results) => {
        if (err) {
            res.status(500).json({ error: "Internal Server Error" });
            return;
        }
        res.json(results);
    });
});

// Start Server
app.listen(5000, () => console.log("Server running on port 5000"));
