$wsh = New-Object -ComObject WScript.Shell
$shortcut = $wsh.CreateShortcut("C:\Users\Artee Admin\Desktop\Easyship Chrome Debug.lnk")
$shortcut.TargetPath = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$shortcut.Arguments = '--remote-debugging-port=9222 --user-data-dir="C:\Users\Artee Admin\AppData\Local\Google\Chrome\User Data" --profile-directory="Profile 1" https://app.easyship.com/shipments?tab_id=purchased'
$shortcut.WorkingDirectory = "C:\Program Files\Google\Chrome\Application"
$shortcut.Save()
Write-Output "Shortcut updated successfully!"
