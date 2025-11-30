<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "Session expired. Please login.";
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Delete message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_message'])) {
    $msg_id = intval($_POST['message_id']);
    
    // Only delete if user is the sender
    $delete = "DELETE FROM messages WHERE id = '$msg_id' AND sender_id = '$user_id'";
    
    if ($conn->query($delete)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}

// Send message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $receiver = mysqli_real_escape_string($conn, $_POST['receiver_id']);
    
    $insert = "INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES ('$user_id', '$receiver', '$message', NOW())";
    
    if ($conn->query($insert)) {
        $new_id = $conn->insert_id;
        echo json_encode(['status' => 'success', 'id' => $new_id, 'time' => date('Y-m-d H:i:s')]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}

// Get messages with timestamp
if (isset($_GET['get_messages']) && isset($_GET['receiver_id'])) {
    $receiver_id = mysqli_real_escape_string($conn, $_GET['receiver_id']);
    
    $messages_sql = "SELECT m.id, m.message, m.sent_at, m.sender_id,
                     CASE WHEN m.sender_id = '$user_id' THEN 'me' ELSE 'them' END as sender_type
                     FROM messages m 
                     WHERE (m.sender_id = '$user_id' AND m.receiver_id = '$receiver_id') 
                     OR (m.sender_id = '$receiver_id' AND m.receiver_id = '$user_id') 
                     ORDER BY m.id ASC";
    
    $messages_result = $conn->query($messages_sql);
    
    $messages = [];
    if ($messages_result) {
        while($msg = $messages_result->fetch_assoc()) {
            $messages[] = [
                'id' => $msg['id'],
                'message' => $msg['message'],
                'sent_at' => $msg['sent_at'],
                'sender_type' => $msg['sender_type'],
                'can_delete' => ($msg['sender_id'] == $user_id)
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['messages' => $messages, 'timestamp' => time()]);
    exit();
}

// Get receiver_id
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : null;

// Get contacts FIRST
if ($role == 'parent') {
    $contacts_sql = "SELECT DISTINCT d.user_id, d.driver_name as name 
                     FROM drivers d 
                     JOIN subscriptions s ON d.id = s.driver_id 
                     JOIN parents p ON s.parent_id = p.id 
                     WHERE p.user_id = '$user_id' AND s.status = 'active'";
} else {
    $contacts_sql = "SELECT DISTINCT p.user_id, p.parent_name as name 
                     FROM parents p 
                     JOIN subscriptions s ON p.id = s.parent_id 
                     JOIN drivers d ON s.driver_id = d.id 
                     WHERE d.user_id = '$user_id' AND s.status = 'active'";
}
$contacts_result = $conn->query($contacts_sql);

// Build contacts array
$contacts_array = [];
if ($contacts_result && $contacts_result->num_rows > 0) {
    while($c = $contacts_result->fetch_assoc()) {
        $contacts_array[] = $c;
    }
}

// Get receiver name
$receiver_name = "";
if ($receiver_id) {
    // Find name from contacts array
    foreach($contacts_array as $contact) {
        if ($contact['user_id'] == $receiver_id) {
            $receiver_name = $contact['name'];
            break;
        }
    }
}

// Auto-select first contact if none selected
if (!$receiver_id && count($contacts_array) > 0) {
    $receiver_id = $contacts_array[0]['user_id'];
    $receiver_name = $contacts_array[0]['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat - KIDSHUTTLE</title>
<link rel="icon" href="favicon.png" type="image/x-icon">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
    }

    body {
        background: #f5f7ff;
        display: flex;
        height: 100vh;
        overflow: hidden;
    }

    .chat-container {
        display: flex;
        width: 100%;
        height: 100%;
    }

    .contacts-sidebar {
        width: 280px;
        background: white;
        border-right: 1px solid #e8edff;
        overflow-y: auto;
    }

    .contacts-header {
        padding: 20px;
        background: #3b5bfd;
        color: white;
        font-weight: 600;
    }

    .contact-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: 0.2s;
    }

    .contact-item:hover {
        background: #f5f7ff;
    }

    .contact-item.active {
        background: #e8edff;
        border-left: 3px solid #3b5bfd;
        font-weight: 600;
    }

    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: white;
    }

    .chat-header {
        padding: 20px;
        background: #3b5bfd;
        color: white;
        font-weight: 600;
        font-size: 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .refresh-indicator {
        font-size: 12px;
        opacity: 0.8;
    }

    .messages-container {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: #f5f7ff;
    }

    .message {
        margin-bottom: 15px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .message.me {
        justify-content: flex-end;
    }

    .message.them {
        justify-content: flex-start;
    }

    .message-content {
        max-width: 60%;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .message-bubble {
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.4;
        word-wrap: break-word;
        position: relative;
    }

    .message.me .message-bubble {
        background: #3b5bfd;
        color: white;
        border-bottom-right-radius: 4px;
    }

    .message.them .message-bubble {
        background: white;
        color: #333;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-bottom-left-radius: 4px;
    }

    .message-time {
        font-size: 11px;
        opacity: 0.7;
        margin-top: 3px;
    }

    .delete-btn {
        background: #e63946;
        color: white;
        border: none;
        padding: 4px 12px;
        border-radius: 5px;
        font-size: 11px;
        cursor: pointer;
        align-self: flex-end;
    }

    .delete-btn:hover {
        background: #d62828;
    }

    .chat-input-container {
        padding: 20px;
        background: white;
        border-top: 1px solid #e8edff;
        display: flex;
        gap: 10px;
    }

    .chat-input {
        flex: 1;
        padding: 12px 16px;
        border: 1px solid #ddd;
        border-radius: 25px;
        font-size: 14px;
        outline: none;
    }

    .chat-input:focus {
        border-color: #3b5bfd;
    }

    .send-btn {
        padding: 12px 24px;
        background: #3b5bfd;
        color: white;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
    }

    .send-btn:hover {
        background: #2c47d6;
    }

    .send-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .no-chat {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #999;
        font-size: 16px;
        text-align: center;
        padding: 20px;
    }
</style>

</head>

<body>

<div class="chat-container">
    
    <div class="contacts-sidebar">
        <div class="contacts-header">
            <?php echo $role == 'parent' ? 'Drivers' : 'Parents'; ?>
        </div>
        
        <?php if (count($contacts_array) > 0): ?>
            <?php foreach($contacts_array as $contact): ?>
                <div class="contact-item <?php echo ($contact['user_id'] == $receiver_id) ? 'active' : ''; ?>" 
                     onclick="location.href='chat.php?receiver_id=<?php echo $contact['user_id']; ?>'">
                    <?php echo htmlspecialchars($contact['name']); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 20px; text-align: center; color: #999;">
                No contacts available
            </div>
        <?php endif; ?>
    </div>

    <div class="chat-main">
        <?php if ($receiver_id): ?>
            <div class="chat-header">
                <span>Chat with <?php echo htmlspecialchars($receiver_name); ?></span>
                <span class="refresh-indicator" id="refresh-status">●</span>
            </div>

            <div class="messages-container" id="messages">
                <div style="text-align: center; color: #999; padding: 20px;">Loading messages...</div>
            </div>

            <div class="chat-input-container">
                <input type="text" class="chat-input" id="message-input" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="send-btn" id="send-btn" onclick="sendMessage()">Send</button>
            </div>
        <?php else: ?>
            <div class="no-chat">
                <p>No active subscriptions.<br>Subscribe to start chatting.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
   const receiverId = <?php echo $receiver_id ? $receiver_id : 'null'; ?>;
   let lastMessageCount = 0;
   let isLoadingMessages = false;
   let retryCount = 0;
   const maxRetries = 3;

   <?php if ($receiver_id): ?>
   // Load immediately
   loadMessages();

   // Auto-refresh every 1 second
   setInterval(loadMessages, 1000);
   <?php endif; ?>

   function loadMessages() {
       if (!receiverId || isLoadingMessages) return;
       
       isLoadingMessages = true;
       
       // Update indicator
       const indicator = document.getElementById('refresh-status');
       if (indicator) indicator.style.color = '#4ade80';
       
       fetch('chat.php?get_messages=1&receiver_id=' + receiverId + '&t=' + Date.now())
       .then(response => {
           if (!response.ok) throw new Error('Network error: ' + response.status);
           return response.json();
       })
       .then(data => {
           if (data.status === 'error') {
               if (data.message.includes('Session expired')) {
                   alert('Your session has expired. Please log in again.');
                   window.location.href = 'login.php';  // Adjust to your login page
               } else {
                   alert('Error loading messages: ' + data.message);
               }
               return;
           }
           
           const messages = data.messages || [];
           
           // Only update if count changed
           if (messages.length !== lastMessageCount) {
               displayMessages(messages);
               lastMessageCount = messages.length;
           }
           
           // Reset indicator and retry count on success
           if (indicator) {
               setTimeout(() => {
                   indicator.style.color = 'white';
               }, 200);
           }
           retryCount = 0;
           isLoadingMessages = false;
       })
       .catch(error => {
           console.error('Error loading messages:', error);
           retryCount++;
           if (retryCount <= maxRetries) {
               setTimeout(loadMessages, 2000);  // Retry after 2 seconds
           } else {
               alert('Failed to load messages after multiple attempts. Please refresh the page.');
               retryCount = 0;
           }
           isLoadingMessages = false;
       });
   }

   function displayMessages(messages) {
       const container = document.getElementById('messages');
       const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
       
       container.innerHTML = '';
       
       if (messages.length === 0) {
           container.innerHTML = '<div style="text-align: center; color: #999; padding: 50px;">No messages yet.<br>Start the conversation!</div>';
       } else {
           messages.forEach(msg => {
               const messageDiv = document.createElement('div');
               messageDiv.className = 'message ' + msg.sender_type;
               
               const contentDiv = document.createElement('div');
               contentDiv.className = 'message-content';
               
               const bubble = document.createElement('div');
               bubble.className = 'message-bubble';
               
               const text = document.createElement('div');
               text.textContent = msg.message;
               
               const time = document.createElement('div');
               time.className = 'message-time';
               time.textContent = formatTime(msg.sent_at);
               
               bubble.appendChild(text);
               bubble.appendChild(time);
               contentDiv.appendChild(bubble);
               
               // Add delete button for own messages
               if (msg.can_delete) {
                   const deleteBtn = document.createElement('button');
                   deleteBtn.className = 'delete-btn';
                   deleteBtn.textContent = 'Delete';
                   deleteBtn.onclick = () => deleteMessage(msg.id);
                   contentDiv.appendChild(deleteBtn);
               }
               
               messageDiv.appendChild(contentDiv);
               container.appendChild(messageDiv);
           });
       }
       
       if (wasAtBottom) {
           container.scrollTop = container.scrollHeight;
       }
   }

   function sendMessage() {
       const input = document.getElementById('message-input');
       const sendBtn = document.getElementById('send-btn');
       const message = input.value.trim();
       
       if (!message || !receiverId) return;
       
       input.disabled = true;
       sendBtn.disabled = true;
       sendBtn.textContent = 'Sending...';
       
       const formData = new URLSearchParams();
       formData.append('send_message', '1');
       formData.append('receiver_id', receiverId);
       formData.append('message', message);
       
       fetch('chat.php', {
           method: 'POST',
           headers: {'Content-Type': 'application/x-www-form-urlencoded'},
           body: formData
       })
       .then(response => {
           if (!response.ok) throw new Error('Network error: ' + response.status);
           return response.json();
       })
       .then(data => {
           if (data.status === 'success') {
               input.value = '';
               loadMessages();  // Load immediately after send
           } else if (data.status === 'error') {
               if (data.message.includes('Session expired')) {
                   alert('Your session has expired. Please log in again.');
                   window.location.href = 'login.php';
               } else {
                   alert('Failed to send message: ' + data.message);
               }
           }
           
           input.disabled = false;
           sendBtn.disabled = false;
           sendBtn.textContent = 'Send';
           input.focus();
       })
       .catch(error => {
           console.error('Error sending message:', error);
           alert('Failed to send message. Please try again.');
           input.disabled = false;
           sendBtn.disabled = false;
           sendBtn.textContent = 'Send';
       });
   }

   function deleteMessage(msgId) {
       if (!confirm('Delete this message?')) return;
       
       const formData = new URLSearchParams();
       formData.append('delete_message', '1');
       formData.append('message_id', msgId);
       
       fetch('chat.php', {
           method: 'POST',
           headers: {'Content-Type': 'application/x-www-form-urlencoded'},
           body: formData
       })
       .then(response => {
           if (!response.ok) throw new Error('Network error: ' + response.status);
           return response.json();
       })
       .then(data => {
           if (data.status === 'success') {
               loadMessages();
           } else if (data.status === 'error') {
               if (data.message.includes('Session expired')) {
                   alert('Your session has expired. Please log in again.');
                   window.location.href = 'login.php';
               } else {
                   alert('Failed to delete message: ' + data.message);
               }
           }
       })
       .catch(error => {
           console.error('Error deleting message:', error);
           alert('Failed to delete message. Please try again.');
       });
   }

   function formatTime(timestamp) {
       const date = new Date(timestamp);
       const hours = date.getHours().toString().padStart(2, '0');
       const minutes = date.getMinutes().toString().padStart(2, '0');
       return hours + ':' + minutes;
   }
   </script>

</body>
</html>