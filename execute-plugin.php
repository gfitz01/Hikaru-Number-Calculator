<?php
/*
Plugin Name: Python
Description: Runs a Python script that finds a path to Hikaru when you click a button using AJAX.
Version: 1.0
Author: John DelPrete
*/

function hikaru_button_shortcode() {
  ob_start();
  ?>
  <div id="python-output"></div>
  <button id="run-python">Find Path</button>
  <div id="loading-message" style="display:none;">Running, please wait...</div>

  <script>
  document.getElementById("run-python").addEventListener("click", function () {
    const output = document.getElementById("python-output");
    const loadingMessage = document.getElementById("loading-message");
    
    //shows loading message and hides button
    loadingMessage.style.display = 'block';
    output.innerHTML = "";
    
    console.log("AJAX request started...");
    
    //sends AJAX request
    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "action=run_hikaru_script"
    })
    .then(response => response.text())
    .then(data => {
        console.log("AJAX response received");
        console.log(data); 
        output.innerHTML = `<pre>${data}</pre>`;
        loadingMessage.style.display = 'none';  //hides loading message
    })
    .catch(error => {
        console.error("AJAX error:", error);
        output.innerHTML = "Error running script.";
        loadingMessage.style.display = 'none';  //hides loading message
    });
});


  </script>
  <?php
  return ob_get_clean();
}

//interfaces with wordpress to add button functionality
add_shortcode('hikaru_button', 'hikaru_button_shortcode');

function handle_hikaru_ajax() {
    ini_set('max_execution_time', 0);  //remove time limit
    $python_path = '/Library/Frameworks/Python.framework/Versions/3.13/bin/python3';
    $file = __DIR__ . '/played-hikaru.py';
    $csv_file = realpath(__DIR__ . '/played-hikaru.csv'); //returns full path, wasn't working otherwise

    if (!file_exists($file)) {
      error_log("Python file not found at: " . $file);
      echo "Python file not found.";
      wp_die();  //stops execution
    }

    if (!file_exists($csv_file)) {
      error_log("CSV file not found at: " . $csv_file);
      echo "CSV file not found.";
      wp_die(); //stops execution
    }


    $command = escapeshellcmd($python_path) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg($csv_file);
    error_log("Running command: " . $command);

    $output = shell_exec($command);

    //was used for debugging
    if ($output === null) {
      error_log("Python script failed to execute");
      echo "Error running Python script.";
    } else {
      error_log("Python script output: " . $output);
      echo $output;
    }
    wp_die(); //ends AJAX
}

#wp_ajax handle requests with AJAX. run_hikaru_script is the previously specified body for POST
add_action('wp_ajax_run_hikaru_script', 'handle_hikaru_ajax');
add_action('wp_ajax_nopriv_run_hikaru_script', 'handle_hikaru_ajax'); //for non-logged-in users
