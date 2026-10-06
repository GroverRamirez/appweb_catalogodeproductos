; =============================================================================
;  Instalador de PC Store Pro (Inno Setup 6)
;
;  Requiere que antes se haya armado la carpeta portable:
;     powershell -ExecutionPolicy Bypass -File instalador\build-portable.ps1
;
;  Despues: abrir este archivo con Inno Setup y presionar Compilar (F9).
;  El instalador queda en dist\PCStorePro-Setup.exe
;
;  Se instala en la carpeta del usuario (no en Archivos de programa) a
;  proposito: la app escribe su base de datos y sus imagenes dentro de su
;  propia carpeta, y ahi no hace falta pedir permisos de administrador.
; =============================================================================

#define MiApp       "PC Store Pro"
#define MiVersion   "1.0"
#define MiEditor    "Instituto Tecnologico Eterazama"
#define MiEjecutable "Iniciar PC Store Pro.bat"

[Setup]
AppName={#MiApp}
AppVersion={#MiVersion}
AppPublisher={#MiEditor}
DefaultDirName={localappdata}\{#MiApp}
DefaultGroupName={#MiApp}
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=..\dist
OutputBaseFilename=PCStorePro-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesInstallIn64BitMode=x64compatible

[Languages]
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[Tasks]
Name: "desktopicon"; Description: "Crear un acceso directo en el escritorio"; GroupDescription: "Accesos directos:"

[Files]
Source: "..\dist\PC Store Pro\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{group}\{#MiApp}"; Filename: "{app}\{#MiEjecutable}"; WorkingDir: "{app}"
Name: "{group}\Detener {#MiApp}"; Filename: "{app}\Detener PC Store Pro.bat"; WorkingDir: "{app}"
Name: "{group}\Desinstalar {#MiApp}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MiApp}"; Filename: "{app}\{#MiEjecutable}"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MiEjecutable}"; Description: "Abrir {#MiApp} ahora"; Flags: postinstall shellexec skipifsilent nowait

[UninstallDelete]
; Lo que la app genera despues de instalada: sin esto, la desinstalacion deja
; la carpeta con la base de datos y los logs adentro.
Type: filesandordirs; Name: "{app}\app\storage"
Type: filesandordirs; Name: "{app}\app\bootstrap\cache"
Type: files; Name: "{app}\app\database\database.sqlite"
