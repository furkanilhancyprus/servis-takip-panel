!macro customInstall
  ; Visual C++ 2015-2022 Redistributable kur (sessiz)
  IfFileExists "$INSTDIR\resources\vc_redist.x64.exe" 0 +3
    ExecWait '"$INSTDIR\resources\vc_redist.x64.exe" /install /quiet /norestart'
    Delete "$INSTDIR\resources\vc_redist.x64.exe"
!macroend
