$wsh = New-Object -ComObject WScript.Shell
$shortcut = $wsh.CreateShortcut("C:\Users\Artee Admin\Desktop\Sayantan - Chrome.lnk")
Write-Output "TargetPath: $($shortcut.TargetPath)"
Write-Output "Arguments: $($shortcut.Arguments)"
Write-Output "WorkingDirectory: $($shortcut.WorkingDirectory)"
