<?php
/*
Plugin Name: Python
Description: Runs a Python script that finds a path to Hikaru when you enter a username.
Version: 1.0
Author: John DelPrete
*/

// 
function hikaru_button_shortcode() {
  ob_start();
  ?>

<style>
    #hikaru-form {
      margin: 20px 0;
      padding: 20px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background-color: #f9f9f9;
      max-width: 400px;
    }

    #hikaru-form label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
    }

    #hikaru-form input[type="text"] {
      width: 100%;
      padding: 8px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }

    #hikaru-form button {
      background-color: #0073aa;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
    }
  </style>

  <form id="hikaru-form">
    <label for="username_input">Enter Username:</label>
    <input type="text" id="username_input" name="username" required>
    <button type="submit">Submit</button>
  </form>

  <div id="loading-message" style="display:none;">Search should start in a few seconds.</div>
  <div id="python-output"></div>

  <script>
document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("hikaru-form").addEventListener("submit", function (e) {
    e.preventDefault();

    const username = document.getElementById("username_input").value;
    const output = document.getElementById("python-output");
    const loading = document.getElementById("loading-message");

    output.innerHTML = "";
    loading.style.display = "block";

    const controller = new AbortController();
    const timeout = setTimeout(() => {
      controller.abort();
    }, 600000); // 10 minutes

    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: new URLSearchParams({
        action: "run_hikaru_script",
        username: username
      }),
      signal: controller.signal
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        clearTimeout(timeout);

        loading.style.display = "none";
        
        if (data.error) {
          output.innerHTML = `<pre style="color:red;">${data.error}</pre>`;
        } else {
          output.innerHTML = `<pre>${data.log || 'Started search...'}</pre>`;
          pollScriptStatus(); // starts polling
        }
      } catch (e) {
        output.innerHTML = `<pre style="color:red;">Unexpected response:\n${text}</pre>`;
        console.error("JSON parse error:", e);
      }
    }) 
    .catch(err => {
      clearTimeout(timeout);
      output.innerHTML = "An error occurred: " + err.message;
      console.error("Fetch error:", err);
    });

    function pollScriptStatus() {
      const statusInterval = setInterval(() => {
        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded"
          },
          body: new URLSearchParams({
            action: "check_hikaru_script_status"
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.log) {
            output.innerHTML = `<pre>${data.log}</pre>`;
          }
          if (data.done || data.error) {
            clearInterval(statusInterval);
            loading.style.display = "none";
          }
        })
        .catch(err => {
          console.error("Polling error:", err);
          clearInterval(statusInterval);
          output.innerHTML = `<pre style="color:red;">Polling failed.</pre>`;
        });
      }, 5000); // every 5 seconds
    }
  });
});
</script>
  <?php
  return ob_get_clean();
}

//interfaces with wordpress to add button functionality
add_shortcode('hikaru_button', 'hikaru_button_shortcode');

function handle_hikaru_ajax() {
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        wp_send_json_error(['error' => 'Unauthorized access.']);
    }

    // prevents whitespace or warnings before JSON
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    ini_set('max_execution_time', 0);
    ignore_user_abort(true);
    set_time_limit(0);

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    if (empty($username)) {
        wp_send_json_error(['error' => 'No username provided.']);
    }

    $python_path = '/usr/bin/python3'; // path for the server
    $file = __DIR__ . '/played-hikaru.py';
    $csv_file = realpath(__DIR__ . '/played-hikaru.csv');
    $log_file = '/tmp/hikaru_script.log';

    // clears contents of log file so it can be reused
    @unlink($log_file);

    $command = escapeshellcmd($python_path) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg($csv_file) . ' ' . escapeshellarg($username);
    $command .= ' > ' . escapeshellarg($log_file) . ' 2>&1 &';

    error_log("Executing command: $command");
    shell_exec($command);

    wp_send_json_success(['status' => 'Job started successfully.', 'log' => null]);
}

// wp_ajax handle requests with AJAX. run_hikaru_script is the previously specified body for POST
add_action('wp_ajax_run_hikaru_script', 'handle_hikaru_ajax');
add_action('wp_ajax_nopriv_run_hikaru_script', 'handle_hikaru_ajax'); //for non-logged-in users

function check_hikaru_script_status() {
    header('Content-Type: application/json');

    $log_file = '/tmp/hikaru_script.log';
    $log_content = file_get_contents($log_file);

    if ($log_content === false) {
        echo json_encode(['error' => 'Log file not found.']);
        wp_die();
    }

    // checks for completion condition (for example, you can check for a specific line in the log file)
    $done = strpos($log_content, 'SCRIPT FINISHED') !== false;

    echo json_encode([
        'status' => 'Job is running...',
        'log' => $log_content,
        'done' => $done
    ]);
    wp_die();
}

// hook for checking status
add_action('wp_ajax_check_hikaru_script_status', 'check_hikaru_script_status');
add_action('wp_ajax_nopriv_check_hikaru_script_status', 'check_hikaru_script_status');