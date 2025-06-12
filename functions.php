<?php

// Database connection function
function getDbConnection() {
    $host = "localhost";
    $username = "root";
    $password = "";
    $dbname = "test";
    
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Σφάλμα σύνδεσης με βάση δεδομένων: " . $conn->connect_error);
    }
    return $conn;
}

// Create the board if user is logged in
function initializeBoard() {
    if (isset($_SESSION['user_id']) && !isset($_SESSION['board'])) {
        $_SESSION['board'] = array_fill(0, 20, array_fill(0, 20, '-'));
        $_SESSION['pieces_placed_count'] = 0; // Μετρητής για τον έλεγχο της πρώτης κίνησης
        // Προσομοίωση διαθέσιμων κομματιών (αυτά θα ήταν όλα τα 21 κομμάτια του Blokus)
        // Για απλότητα, χρησιμοποιούμε τους ίδιους L, I, O
        $_SESSION['available_pieces'] = [
            'L1' => [['-', '-', 'L'], ['L', 'L', 'L']], // L-κομμάτι
            'I4' => [['I'], ['I'], ['I'], ['I']], // I-κομμάτι (4 τετράγωνα)
            'O' => [['O', 'O'], ['O', 'O']], // O-κομμάτι (2x2)
            // Προσθέστε κι άλλα κομμάτια εδώ για πλήρες παιχνίδι
            // 'T1' => [['T', 'T', 'T'], ['-', 'T', '-']],
            // 'S1' => [['-', 'S', 'S'], ['S', 'S', '-']],
            // 'Z1' => [['Z', 'Z', '-'], ['-', 'Z', 'Z']]
        ];
        $_SESSION['used_pieces'] = []; // Τα κομμάτια που έχουν χρησιμοποιηθεί
    }
}

// Handle user registration
function registerUser($conn) {
    if (isset($_POST['register'])) {
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Έλεγχος αν το username ή το email υπάρχει ήδη
        $checkQuery = "SELECT id FROM Users WHERE username = ? OR email = ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("ss", $username, $email);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            echo "<p style='color:red;'>Το όνομα χρήστη ή το email υπάρχει ήδη. Παρακαλώ επιλέξτε άλλο.</p>";
        } else {
            $query = "INSERT INTO Users (firstname, lastname, email, username, password, score) VALUES (?, ?, ?, ?, ?, 0)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $firstname, $lastname, $email, $username, $password);

            if ($stmt->execute()) {
                echo "<p style='color:green;'>Η εγγραφή ολοκληρώθηκε με επιτυχία!</p>";
            } else {
                echo "<p style='color:red;'>Σφάλμα κατά την εγγραφή: " . $stmt->error . "</p>";
            }
        }
        $stmtCheck->close();
    }
}

// Handle user login
function loginUser($conn) {
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $query = "SELECT id, password FROM Users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                // Επανα-αρχικοποίηση του πίνακα και των κομματιών στην σύνδεση
                // για να ξεκινήσει ένα νέο παιχνίδι ή να φορτώσει το προηγούμενο
                // (η φόρτωση προηγούμενου παιχνιδιού θα απαιτούσε επιπλέον λογική)
                $_SESSION['board'] = array_fill(0, 20, array_fill(0, 20, '-'));
                $_SESSION['pieces_placed_count'] = 0;
                $_SESSION['available_pieces'] = [
                    'L1' => [['-', '-', 'L'], ['L', 'L', 'L']], 
                    'I4' => [['I'], ['I'], ['I'], ['I']], 
                    'O' => [['O', 'O'], ['O', 'O']],
                ];
                $_SESSION['used_pieces'] = [];

                echo "<p style='color:green;'>Συνδεθήκατε με επιτυχία!</p>";
            } else {
                echo "<p style='color:red;'>Λάθος κωδικός πρόσβασης.</p>";
            }
        } else {
            echo "<p style='color:red;'>Το όνομα χρήστη δεν υπάρχει.</p>";
        }
    }
}

// Handle user logout
function logoutUser() {
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit;
    }
}


// Display the game board
function displayBoard() {
    echo "<table border='1' style='border-collapse: collapse; text-align: center; border-color: #ccc; margin-top: 20px;'>";
    echo "<thead><tr><th></th>"; // Άδειο κελί για την αριστερή πάνω γωνίααα
    for ($col = 0; $col < 20; $col++) {
        echo "<th style='width: 25px; height: 25px; background-color: #f0f0f0;'>" . $col . "</th>";
    }
    echo "</tr></thead>";
    echo "<tbody>";
    for ($i = 0; $i < 20; $i++) {
        echo "<tr>";
        echo "<th style='width: 25px; height: 25px; background-color: #f0f0f0;'>" . $i . "</th>"; // Αρίθμηση γραμμών
        for ($j = 0; $j < 20; $j++) {
            $cell = $_SESSION['board'][$i][$j];
            $color = '';
            // Ανάλογα με τον χαρακτήρα του κομματιού, δίνουμε χρώμα
            switch ($cell) {
                case 'L': case 'I': case 'O':
                    $color = 'background-color: lightblue;'; // Χρώμα για τα κομμάτια του παίκτη
                    break;
                default:
                    $color = 'background-color: white;'; // Κενά κελιά
            }
            echo "<td style='width: 25px; height: 25px; border: 1px solid #eee; " . $color . "'>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
}

// Helper: Check if a cell is within board boundaries
function isValidCell($r, $c) {
    return $r >= 0 && $r < 20 && $c >= 0 && $c < 20;
}


// Helper: Check if the piece touches another piece of the same type by side
function touchesSide($board, $piece, $y, $x, $pieceChar) {
    $pieceHeight = count($piece);
    $pieceWidth = count($piece[0]);

    for ($i = 0; $i < $pieceHeight; $i++) {
        for ($j = 0; $j < $pieceWidth; $j++) {
            if ($piece[$i][$j] == $pieceChar) { // Only check actual piece cells
                $boardY = $y + $i;
                $boardX = $x + $j;

                // Check adjacent cells (up, down, left, right) for own piece parts
                if (isValidCell($boardY - 1, $boardX) && $board[$boardY - 1][$boardX] == $pieceChar) return true;
                if (isValidCell($boardY + 1, $boardX) && $board[$boardY + 1][$boardX] == $pieceChar) return true;
                if (isValidCell($boardY, $boardX - 1) && $board[$boardY][$boardX - 1] == $pieceChar) return true;
                if (isValidCell($boardY, $boardX + 1) && $board[$boardY][$boardX + 1] == $pieceChar) return true;
            }
        }
    }
    return false;
}


// Helper: Check if the piece touches another piece of the same type by corner
function touchesCorner($board, $piece, $y, $x, $pieceChar) {
    $pieceHeight = count($piece);
    $pieceWidth = count($piece[0]);
    $foundCornerContact = false;

    for ($i = 0; $i < $pieceHeight; $i++) {
        for ($j = 0; $j < $pieceWidth; $j++) {
            if ($piece[$i][$j] == $pieceChar) { // Only check actual piece cells
                $boardY = $y + $i;
                $boardX = $x + $j;

                // Check diagonal cells (all 4 corners) for own piece parts
                if (isValidCell($boardY - 1, $boardX - 1) && $board[$boardY - 1][$boardX - 1] == $pieceChar) $foundCornerContact = true;
                if (isValidCell($boardY - 1, $boardX + 1) && $board[$boardY - 1][$boardX + 1] == $pieceChar) $foundCornerContact = true;
                if (isValidCell($boardY + 1, $boardX - 1) && $board[$boardY + 1][$boardX - 1] == $pieceChar) $foundCornerContact = true;
                if (isValidCell($boardY + 1, $boardX + 1) && $board[$boardY + 1][$boardX + 1] == $pieceChar) $foundCornerContact = true;
            }
        }
    }
    return $foundCornerContact;
}

// Function to calculate score based on pieces placed
function calculateScore() {
    $score = 0;
    // For Blokus, score is typically the number of squares placed
    // Plus bonuses for placing all pieces or ending with small pieces
    // For simplicity, we'll count all placed squares
    foreach ($_SESSION['board'] as $row) {
        foreach ($row as $cell) {
            if ($cell != '-') {
                $score++;
            }
        }
    }
    return $score;
}
    

// Function to place the piece on the board with all Blokus rules
function placePiece($pieceKey, $piece, $x, $y) {
    $pieceHeight = count($piece);
    $pieceWidth = count($piece[0]);
    $board = $_SESSION['board'];
    // Ο χαρακτηρισμός του κομματιού θα είναι ο πρώτος χαρακτήρας του key του κομματιού
    // π.χ., για το 'L1' θα είναι 'L'
    $pieceChar = $pieceKey[0]; 

    // --- Βήμα 1: Έλεγχος αν το κομμάτι έχει ήδη χρησιμοποιηθεί ---
    if (isset($_SESSION['used_pieces'][$pieceKey])) {
        echo "<p style='color:red;'>Αυτό το κομμάτι έχει ήδη χρησιμοποιηθεί.</p>";
        return;
    }

    // --- Βήμα 2: Έλεγχος αν το κομμάτι χωράει στο ταμπλό ---
    if ($x < 0 || $y < 0 || $x + $pieceWidth > 20 || $y + $pieceHeight > 20) {
        echo "<p style='color:red;'>Το κομμάτι δεν χωράει στο ταμπλό στις καθορισμένες θέσεις.</p>";
        return;
    }

    // --- Βήμα 3: Έλεγχος για επικάλυψη με υπάρχοντα κομμάτια ---
    for ($i = 0; $i < $pieceHeight; $i++) {
        for ($j = 0; $j < $pieceWidth; $j++) {
            if ($piece[$i][$j] != '-' && $board[$y + $i][$x + $j] != '-') {
                echo "<p style='color:red;'>Η θέση είναι κατειλημμένη. Προσπαθήστε σε άλλη θέση.</p>";
                return;
            }
        }
    }

    // --- Βήμα 4: Εφαρμογή κανόνων Blokus ---
    $piecesPlacedCount = $_SESSION['pieces_placed_count'];

    if ($piecesPlacedCount === 0) { // Αυτή είναι η πρώτη κίνηση του παίκτη
        // Κανόνας 1: Το πρώτο κομμάτι πρέπει να καλύπτει μια γωνία
        $corners = [
            ['x' => 0, 'y' => 0],
            ['x' => 19, 'y' => 0],
            ['x' => 0, 'y' => 19],
            ['x' => 19, 'y' => 19]
        ];
        $isCornerCovered = false;
        for ($i = 0; $i < $pieceHeight; $i++) {
            for ($j = 0; $j < $pieceWidth; $j++) {
                if ($piece[$i][$j] != '-') { // Αν είναι μέρος του κομματιού
                    $currentX = $x + $j;
                    $currentY = $y + $i;
                    foreach ($corners as $corner) {
                        if ($currentX == $corner['x'] && $currentY == $corner['y']) {
                            $isCornerCovered = true;
                            break 2; // Έξοδος από τα loops
                        }
                    }
                }
            }
        }
        if (!$isCornerCovered) {
            echo "<p style='color:red;'>Το πρώτο σας κομμάτι πρέπει να καλύπτει μία από τις τέσσερις γωνίες του ταμπλό.</p>";
            return;
        }

    } else { // Επόμενες κινήσεις
        // Κανόνας 2: Πρέπει να αγγίζει τουλάχιστον μία γωνία ενός δικού του κομματιού
        if (!touchesCorner($board, $piece, $y, $x, $pieceChar)) {
            echo "<p style='color:red;'>Κάθε επόμενο κομμάτι πρέπει να αγγίζει τουλάχιστον μία γωνία ενός δικού σας κομματιού που βρίσκεται ήδη στο ταμπλό.</p>";
            return;
        }

        // Κανόνας 3: Δεν πρέπει να αγγίζει δικά του κομμάτια κατά μήκος μιας πλευράς
        if (touchesSide($board, $piece, $y, $x, $pieceChar)) {
            echo "<p style='color:red;'>Δεν επιτρέπεται να αγγίζετε δικά σας κομμάτια κατά μήκος μιας πλευράς.</p>";
            return;
        }
    }
   
    // --- Βήμα 5: Τοποθέτηση του κομματιού στον πίνακα ---
    for ($i = 0; $i < $pieceHeight; $i++) {
        for ($j = 0; $j < $pieceWidth; $j++) {
            if ($piece[$i][$j] != '-') { // Place only the actual piece parts
                $_SESSION['board'][$y + $i][$x + $j] = $piece[$i][$j];
            }
        }
    }

    $_SESSION['pieces_placed_count']++; // Αυξάνουμε τον μετρητή κομματιών
    $_SESSION['used_pieces'][$pieceKey] = true; // Σημειώνουμε το κομμάτι ως χρησιμοποιημένο

    echo "<p style='color:green;'>Το κομμάτι τοποθετήθηκε επιτυχώς!</p>";
}
 
// Function to update piece position in the database (this is more for logging moves)
function updatePieceInDb($conn, $user_id, $pieceKey, $x, $y) {
    // Στο Blokus, δεν ενημερώνουμε απλά μια θέση, αλλά καταγράφουμε την κίνηση.
    // Επειδή ο πίνακας αποθηκεύεται στη session, η βάση δεδομένων μπορεί να χρησιμοποιηθεί για
    // ιστορικό κινήσεων ή για αποθήκευση της συνολικής κατάστασης του πίνακα (πιο σύνθετο).
    // Για απλότητα, καταγράφουμε την τελευταία κίνηση.
    // Ένας πιο ολοκληρωμένος τρόπος θα ήταν να αποθηκεύεται ολόκληρος ο πίνακας σε JSON/string
    // ή κάθε κίνηση σε μια λίστα κινήσεων.

    $query = "INSERT INTO GameState (user_id, piece_key, x, y, timestamp) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo "<p style='color:red;'>Σφάλμα προετοιμασίας δήλωσης: " . $conn->error . "</p>";
        return;
    }
    $stmt->bind_param("isii", $user_id, $pieceKey, $x, $y);

    if ($stmt->execute()) {
        // Optionally, update user's score in the Users table
        $score = calculateScore();
        $updateScoreQuery = "UPDATE Users SET score = ? WHERE id = ?";
        $stmtScore = $conn->prepare($updateScoreQuery);
        $stmtScore->bind_param("ii", $score, $user_id);
        $stmtScore->execute();
        $stmtScore->close();

        echo "<p style='color:green;'>Η κίνηση καταγράφηκε και το σκορ ενημερώθηκε.</p>";
    } else {
        echo "<p style='color:red;'>Σφάλμα κατά την καταγραφή της κίνησης: " . $stmt->error . "</p>";
    }
    $stmt->close();
}


function rotatePiece($piece) {
    if (empty($piece)) {
        return [];
    }
    $rows = count($piece);
    $cols = count($piece[0]);
    $rotatedPiece = array_fill(0, $cols, array_fill(0, $rows, '-'));

    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            $rotatedPiece[$j][$rows - 1 - $i] = $piece[$i][$j];
        }
    }
    return $rotatedPiece;
}

?>