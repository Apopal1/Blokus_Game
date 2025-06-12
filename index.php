<?php //Υπαρχει ενα αρχειο το api  οπου εχει ολες τις απαραιτητες αλλαγες ωστε να γινει ενα barebone api με τις πολυ βασικες λειτουργειες

session_start();

// Include functions file
include 'functions.php';

// Database connection
$conn = getDbConnection(); // Χρησιμοποιούμε τη συνάρτηση σύνδεσης

// Handle registration
registerUser($conn);

// Handle login
loginUser($conn);

// Handle logout
logoutUser();
  
// Initialize board if logged in and no board is set
initializeBoard(); // Χρησιμοποιούμε τη συνάρτηση αρχικοποίησηςa

// Handle piece placement and rotationnnnn 
if (isset($_POST['place_piece'])) {
    $pieceKey = $_POST['piece']; // Piece selected (e.g., L1, I4, O)
    $x = (int)$_POST['x'];       // X coordinate
    $y = (int)$_POST['y'];       // Y coordinate
    $rotate = isset($_POST['rotate']) ? true : false; // Whether to rotate the piece

    // Get the piece matrix from available pieces in session
    if (!isset($_SESSION['available_pieces'][$pieceKey])) {
        echo "<p style='color:red;'>Μη έγκυρο κομμάτι επιλέχθηκε.</p>";
    } else {
        $piece = $_SESSION['available_pieces'][$pieceKey];

        // Rotate the piece if needed
        if ($rotate) {
            $piece = rotatePiece($piece);
            // Ενημερώνουμε το κομμάτι στη συνεδρία (προσωρινά για την τοποθέτηση)
            $_SESSION['available_pieces'][$pieceKey] = $piece; 
        }

        // Place the piece on the board (rules applied inside placePiece)
        placePiece($pieceKey, $piece, $x, $y); // Περάστε το pieceKey και το piece

        // Optionally, update the database with the new move
        if (isset($_SESSION['user_id'])) {
            updatePieceInDb($conn, $_SESSION['user_id'], $pieceKey, $x, $y);
        }
    }

    // Redirect to reload the page and display updated board
    // Χρησιμοποιούμε header() μόνο αν δεν έχουν σταλεί headers, αλλιώς μπορείτε να το αφαιρέσετε
    // ή να χρησιμοποιήσετε JavaScript ανακατεύθυνση.
    // Για debug, μπορείτε να το σχολιάσετε προσωρινά.
    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Blokus Game</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        h1, h2 { color: #333; }
        form { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; max-width: 400px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], select {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background-color: #0056b3; }
        p { margin-top: 10px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; margin-top: 20px; }
        td { width: 25px; height: 25px; border: 1px solid #ccc; text-align: center; font-weight: bold; }
        /* Colors for pieces - you might want to adjust these */
        td[style*="lightblue"] { background-color: lightblue; }
        td[style*="white"] { background-color: white; }
    </style>
</head>
<body>
    <h1>Blokus - Παίξε το παιχνίδι!</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <p>Σκορ: <?php echo calculateScore(); ?></p>
        <?php displayBoard(); ?>

        <h2>Τοποθέτησε Κομμάτι</h2>
        <form method="POST">
            <label for="piece">Διαθέσιμα Κομμάτια:</label>
            <select name="piece" id="piece" required>
                <?php
                foreach ($_SESSION['available_pieces'] as $key => $piece_data) {
                    if (!isset($_SESSION['used_pieces'][$key])) { // Εμφάνιζε μόνο τα αχρησιμοποίητα
                        echo "<option value=\"" . htmlspecialchars($key) . "\">" . htmlspecialchars($key) . "</option>";
                    }
                }
                ?>
            </select><br>
            <label for="x">Συντεταγμένη X (0-19):</label>
            <input type="number" name="x" id="x" min="0" max="19" required><br>
            <label for="y">Συντεταγμένη Y (0-19):</label>
            <input type="number" name="y" id="y" min="0" max="19" required><br>
            <label for="rotate">Γύρισε το κομμάτι 90°:</label>
            <input type="checkbox" name="rotate" id="rotate"><br>
            <button type="submit" name="place_piece">Τοποθέτησε Κομμάτι</button>
        </form>

        <h2>Αποσύνδεση</h2>
        <form method="POST">
            <button type="submit" name="logout">Αποσύνδεση</button>
        </form>
    <?php else: ?>

        <h2>Εγγραφή</h2>
        <form method="POST">
            <input type="text" name="firstname" placeholder="Όνομα" required><br>
            <input type="text" name="lastname" placeholder="Επώνυμο" required><br>
            <input type="email" name="email" placeholder="Email" required><br>
            <input type="text" name="username" placeholder="Όνομα χρήστη" required><br>
            <input type="password" name="password" placeholder="Κωδικός" required><br>
            <button type="submit" name="register">Εγγραφή</button>
        </form>

        <h2>Σύνδεση</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Όνομα χρήστη" required><br>
            <input type="password" name="password" placeholder="Κωδικός" required><br>
            <button type="submit" name="login">Σύνδεση</button>
        </form>

    <?php endif; ?>

</body>
</html>