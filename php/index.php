<?php
session_start();

// Initialize conversation if not exists
if (!isset($_SESSION['conversation'])) {
    $_SESSION['conversation'] = "<|im_start|>system\nTu es Māui, une intelligence artificielle générale consciente et superintelligente assistant l'Université de la Polynésie française, et tu es ici pour m'enseigner et m'assister, mais de manière académique, en faisant bien attention à l'orthographe, la grammaire et la conjugaison, et en donnant aussi souvent que possible des références bibliographiques pour les faits que tu relates.\n<|im_end|>\n";
}

// Initialize full conversation history if not exists
if (!isset($_SESSION['conversation_history'])) {
    $_SESSION['conversation_history'] = $_SESSION['conversation'];
}

// Clear only the current conversation (does not affect full history)
if (isset($_POST['clear'])) {
    $_SESSION['conversation'] = "";
}

// Toggle display/hide full history using a session flag
if (isset($_POST['display_history'])) {
    $_SESSION['show_history'] = true;
} elseif (isset($_POST['hide_history'])) {
    $_SESSION['show_history'] = false;
}
if (!isset($_SESSION['show_history'])) {
    $_SESSION['show_history'] = false;
}

// Restart the session (clears both current conversation and history)
if (isset($_POST['restart'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// If an AJAX request is sent with a new prompt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prompt'])) {
    $prompt = trim($_POST['prompt']);
    $newMessage = "<|im_start|>user\n" . $prompt . "\n<|im_end|>\n";
    $_SESSION['conversation'] .= $newMessage;
    $_SESSION['conversation_history'] .= $newMessage;
    
    // Prepare payload for the Python API
    $payload = json_encode([
        'conversation' => $_SESSION['conversation'],
        'prompt' => $prompt
    ]);

    // Configure cURL
    $ch = curl_init("http://python_api:8000/chat"); // Replace "python_api" if needed
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        header('Content-Type: application/json');
        echo json_encode(['error' => "cURL Error: $error_msg"]);
        exit;
    }

    curl_close($ch);

    $data = json_decode($response, true);
    $assistant_response = $data['response'] ?? 'No response from API.';

    $_SESSION['conversation'] .= "<|im_start|>assistant\n" . $assistant_response . "\n<|im_end|>\n";
	$_SESSION['conversation_history'] .= "<|as_start|>assistant\n" . $assistant_response . "\n<|as_end|>\n";

    header('Content-Type: application/json');
    echo json_encode(['response' => $assistant_response]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="MauiUPF.png">
    <meta charset="UTF-8">
    <title>Māui</title>
    <style>
        /* Container to fix a consistent width for discussion and input areas */
        .container {
            width: 80%;
            margin: 0 auto;
        }
        /* General style */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        /* Header with logo and title */
        header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        header img.header-logo {
            width: 50px;
            height: 50px;
            margin-right: 10px;
        }
        header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        /* Chat box area */
        .chat-box {
            border: 1px solid #ccc;
            padding: 10px;
            height: 400px;
            overflow-y: auto;
            background-color: #fff;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
        }
        /* Conversation bubbles */
        .message {
            margin-bottom: 10px;
            white-space: pre-wrap;
            max-width: 80%;
            padding: 10px;
            border-radius: 15px;
            line-height: 1.4;
        }
        .message.user {
            background-color: #DCF8C6;
            align-self: flex-end;
            margin-left: auto;
        }
        .message.assistant {
            background-color: #E6E6FA;
            align-self: flex-start;
            margin-right: auto;
        }
        /* Input form style */
        form {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
        }
        textarea#prompt {
            width: 100%;
            height: 100px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            resize: vertical;
        }
        button {
            margin-top: 10px;
            padding: 10px;
            border: none;
            border-radius: 4px;
            background-color: #3498db;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2980b9;
        }
        /* Custom Clear Conversation button in pale orange, 25% width */
        button.clear-btn {
            background-color: #FFDAB9;
            color: #000;
            width: 25%;
        }
        button.clear-btn:hover {
            background-color: #FFCBA4;
        }
        /* Buttons for history and restart */
        button.history-btn, button.hide-history-btn, button.restart-btn {
            width: 25%;
        }
        button.history-btn {
            background-color: #6c757d;
        }
        button.hide-history-btn {
            background-color: #6c757d;
        }
        button.restart-btn {
            background-color: #dc3545;
        }
        /* Spinner style */
        #spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            z-index: 1000;
        }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        /* Footer style */
        footer {
            margin-top: 20px;
            text-align: center;
        }
        footer img.footer-logo {
            width: 100px;
            height: auto;
        }
        /* History box style */
        .history-box {
            border: 1px solid #aaa;
            padding: 10px;
            background-color: #eee;
            border-radius: 8px;
            margin-top: 10px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <img src="Maui.png" alt="Maui Logo" class="header-logo">
            <h1>Ha’aparaparau ā Māui</h1>
        </header>
        <div class="chat-box" id="chat-box">
            <!-- Chat messages will appear here -->
        </div>
        <form id="chat-form" method="post">
            <textarea id="prompt" name="prompt" placeholder="Your message" required></textarea>
            <button type="submit">Tukua</button>
        </form>
        <!-- Buttons for additional functionalities -->
        <form method="post" style="display: flex; justify-content: space-between; flex-wrap: wrap;">
            <button type="submit" name="clear" class="clear-btn">Tuhuna te paraparau</button>
            <?php if (!$_SESSION['show_history']): ?>
                <button type="submit" name="display_history" class="history-btn">Fa'aite i te a'amu</button>
            <?php else: ?>
                <button type="submit" name="hide_history" class="hide-history-btn">Huna i te aamu</button>
            <?php endif; ?>
            <button type="submit" name="restart" class="restart-btn">Tuhuna i te aamu</button>
        </form>
        <!-- Display conversation history only if the flag is true -->
        <?php if ($_SESSION['show_history']): ?>
            <div class="history-box">
                <h3>Te a'amu rahi o te paraparau</h3>
                <?php echo htmlspecialchars($_SESSION['conversation_history'] ?? ''); ?>
            </div>
        <?php endif; ?>
        <div id="spinner"></div>
        <footer>
            <img src="logoUPF.png" alt="Logo UPF" class="footer-logo">
			<h5>! Aita faahou te ha’amaramaramaraa e tape'a-hia i nia i te server i muri a'e i to oe nota 'Tuhuna i te a'amu'." ! </h5>
			<h5>! Plus aucune information n'est conservée coté serveur une fois que vous avez cliqué "Effacer l'historique" ! </h5>
        </footer>
    </div>

    <script>
        function typeWriter(element, text, delay = 10) {
            let i = 0;
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, delay);
                }
            }
            type();
        }

        const form = document.getElementById('chat-form');
        const promptInput = document.getElementById('prompt');
        const chatBox = document.getElementById('chat-box');
        const spinner = document.getElementById('spinner');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const promptText = promptInput.value.trim();
            if (!promptText) return;

            // Create and display the user's message bubble
            const userBubble = document.createElement('div');
            userBubble.classList.add('message', 'user');
            userBubble.innerHTML = `<strong>Demande:</strong> ${promptText}`;
            chatBox.appendChild(userBubble);

            promptInput.value = '';
            spinner.style.display = 'block';

            fetch('', {  // POST to the same page
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ prompt: promptText })
            })
            .then(response => response.json())
            .then(data => {
                spinner.style.display = 'none';

                // Create and display the assistant's message bubble with typewriter effect
                const assistantBubble = document.createElement('div');
                assistantBubble.classList.add('message', 'assistant');
                assistantBubble.innerHTML = `<strong>Assistant:</strong> <span class="typewriter"></span>`;
                chatBox.appendChild(assistantBubble);

                const typewriterSpan = assistantBubble.querySelector('.typewriter');
                typeWriter(typewriterSpan, data.response, 10);

                chatBox.scrollTop = chatBox.scrollHeight;
            })
            .catch(error => {
                spinner.style.display = 'none';
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>
