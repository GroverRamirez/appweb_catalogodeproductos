; =============================================================================
;  Instalador de PC Store Pro (Inno Setup 6)
;
;  Lo compila solo instalador\build-portable.ps1 al final, si encuentra Inno
;  Setup. A mano: armar antes la carpeta con ese script, abrir este archivo
;  con Inno Setup y presionar Compilar (F9). Queda dist\PCStorePro-Setup.exe
;
;  Que hace en la PC del cliente:
;  - Copia PHP y la app a su carpeta de usuario (no a Archivos de programa):
;    la app escribe su base y sus imagenes ahi y no hace falta administrador.
;  - En una instalacion nueva pide el nombre de la tienda y los datos del
;    propietario, y corre "artisan app:instalar": crea la base, aplica las
;    migraciones, carga roles y configuracion y crea al propietario.
;  - Si ya estaba instalada, actualiza los archivos y aplica las migraciones
;    pendientes sin tocar los datos ni pedir nada.
; =============================================================================

#define MiApp        "PC Store Pro"
#define MiVersion    "1.0"
#define MiEditor     "Instituto Tecnologico Eterazama"
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
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayName={#MiApp}

[Languages]
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[Tasks]
Name: "desktopicon"; Description: "Crear un acceso directo en el escritorio"; GroupDescription: "Accesos directos:"

[Files]
; El .env lleva la clave propia de esta instalacion: se escribe solo la
; primera vez y nunca se borra, para que una actualizacion no la cambie.
Source: "..\dist\PC Store Pro\*"; DestDir: "{app}"; Excludes: "\app\.env"; Flags: recursesubdirs createallsubdirs ignoreversion
Source: "..\dist\PC Store Pro\app\.env"; DestDir: "{app}\app"; Flags: onlyifdoesntexist uninsneveruninstall

[Icons]
Name: "{group}\{#MiApp}"; Filename: "{app}\{#MiEjecutable}"; WorkingDir: "{app}"
Name: "{group}\Detener {#MiApp}"; Filename: "{app}\Detener PC Store Pro.bat"; WorkingDir: "{app}"
Name: "{group}\Leeme"; Filename: "{app}\LEEME.txt"
Name: "{group}\Desinstalar {#MiApp}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MiApp}"; Filename: "{app}\{#MiEjecutable}"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MiEjecutable}"; Description: "Abrir {#MiApp} ahora"; Flags: postinstall shellexec skipifsilent nowait

[UninstallDelete]
; Solo lo que se regenera solo. La base, las imagenes y las copias de
; seguridad se quedan salvo que el usuario pida borrarlas al desinstalar.
Type: filesandordirs; Name: "{app}\app\bootstrap\cache"
Type: filesandordirs; Name: "{app}\app\storage\framework"

[Code]
var
  PaginaTienda: TInputQueryWizardPage;
  PaginaPropietario: TInputQueryWizardPage;

function BaseDeDatos(): String;
begin
  Result := ExpandConstant('{app}\app\database\database.sqlite');
end;

function YaInstalada(): Boolean;
begin
  Result := FileExists(BaseDeDatos());
end;

{ Cierra solo los PHP de esta instalacion, para poder reemplazar sus archivos. }
procedure DetenerTienda(Carpeta: String);
var
  Codigo: Integer;
begin
  Exec('powershell.exe',
    '-NoProfile -ExecutionPolicy Bypass -Command "Get-Process php -ErrorAction SilentlyContinue | ' +
    'Where-Object { $_.Path -like ''' + Carpeta + '\*'' } | Stop-Process -Force"',
    '', SW_HIDE, ewWaitUntilTerminated, Codigo);
end;

function JsonTexto(S: String): String;
begin
  StringChangeEx(S, '\', '\\', True);
  StringChangeEx(S, '"', '\"', True);
  StringChangeEx(S, #13, '', True);
  StringChangeEx(S, #10, ' ', True);
  StringChangeEx(S, #9, ' ', True);
  Result := '"' + S + '"';
end;

procedure InitializeWizard();
begin
  PaginaTienda := CreateInputQueryPage(wpSelectDir,
    'Tu tienda', 'Como se llama la tienda?',
    'Es el nombre que ven los clientes en el catalogo. Lo podes cambiar despues en Configuracion.');
  PaginaTienda.Add('Nombre de la tienda:', False);
  PaginaTienda.Values[0] := 'PC Store Pro';

  PaginaPropietario := CreateInputQueryPage(PaginaTienda.ID,
    'Usuario propietario', 'Con este usuario vas a entrar al panel de administracion.',
    'El propietario tiene control total: desde el panel puede crear despues a encargados y vendedores. ' +
    'Guarda bien la contrasena: no se puede recuperar por correo.');
  PaginaPropietario.Add('Tu nombre:', False);
  PaginaPropietario.Add('Correo (es tu usuario para entrar):', False);
  PaginaPropietario.Add('Contrasena (minimo 8 caracteres):', True);
  PaginaPropietario.Add('Repeti la contrasena:', True);
end;

function ShouldSkipPage(PageID: Integer): Boolean;
begin
  { En una actualizacion la tienda y el propietario ya existen. La carpeta
    solo se consulta en estas paginas: antes de elegirla todavia no existe. }
  Result := False;
  if (PageID = PaginaTienda.ID) or (PageID = PaginaPropietario.ID) then
    Result := YaInstalada();
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Correo: String;
begin
  Result := True;

  if CurPageID = PaginaTienda.ID then
  begin
    if Trim(PaginaTienda.Values[0]) = '' then
    begin
      MsgBox('Escribi el nombre de la tienda.', mbError, MB_OK);
      Result := False;
    end;
  end
  else if CurPageID = PaginaPropietario.ID then
  begin
    Correo := Trim(PaginaPropietario.Values[1]);
    if Trim(PaginaPropietario.Values[0]) = '' then
    begin
      MsgBox('Escribi tu nombre.', mbError, MB_OK);
      Result := False;
    end
    else if (Pos('@', Correo) < 2) or (Pos('.', Copy(Correo, Pos('@', Correo), Length(Correo))) = 0) or (Pos(' ', Correo) > 0) then
    begin
      MsgBox('El correo no parece valido. Ejemplo: nombre@tienda.com', mbError, MB_OK);
      Result := False;
    end
    else if Length(PaginaPropietario.Values[2]) < 8 then
    begin
      MsgBox('La contrasena tiene que tener al menos 8 caracteres.', mbError, MB_OK);
      Result := False;
    end
    else if PaginaPropietario.Values[2] <> PaginaPropietario.Values[3] then
    begin
      MsgBox('Las dos contrasenas no coinciden.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  { Si la tienda esta abierta, PHP tiene tomados sus archivos. }
  DetenerTienda(ExpandConstant('{app}'));
  Result := '';
end;

procedure PrepararTienda();
var
  Php, Datos, Parametros, Log: String;
  Lineas: TArrayOfString;
  Codigo: Integer;
  Nueva: Boolean;
begin
  Nueva := not YaInstalada();
  Php := ExpandConstant('{app}\php\php.exe');
  Parametros := '"' + ExpandConstant('{app}\app\artisan') + '" app:instalar --no-interaction';

  if Nueva then
  begin
    { Los datos van en un archivo y no en la linea de comandos, donde se
      verian listando procesos. El comando lo borra apenas lo lee. }
    Datos := ExpandConstant('{app}\app\storage\instalacion.json');
    SetArrayLength(Lineas, 1);
    Lineas[0] := '{"tienda":' + JsonTexto(Trim(PaginaTienda.Values[0])) +
      ',"nombre":' + JsonTexto(Trim(PaginaPropietario.Values[0])) +
      ',"email":' + JsonTexto(Trim(PaginaPropietario.Values[1])) +
      ',"password":' + JsonTexto(PaginaPropietario.Values[2]) + '}';
    SaveStringsToUTF8File(Datos, Lineas, False);
    Parametros := Parametros + ' --datos="' + Datos + '"';
  end;

  WizardForm.StatusLabel.Caption := 'Preparando la base de datos y el usuario propietario...';

  if not Exec(Php, Parametros, ExpandConstant('{app}\app'), SW_HIDE, ewWaitUntilTerminated, Codigo) or (Codigo <> 0) then
  begin
    DeleteFile(Datos);
    Log := ExpandConstant('{app}\app\storage\logs\instalacion.log');
    MsgBox('No se pudo terminar de preparar la tienda.' + #13#10#13#10 +
      'El detalle quedo en:' + #13#10 + Log + #13#10#13#10 +
      'Podes volver a correr el instalador: no se pierde nada.', mbCriticalError, MB_OK);
    { Si era la primera vez, se descarta la base a medio hacer: asi el
      proximo intento vuelve a pedir la tienda y el propietario. En una
      actualizacion la base tiene datos y no se toca. }
    if Nueva then
      DeleteFile(BaseDeDatos());
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
    PrepararTienda();
end;

function InitializeUninstall(): Boolean;
begin
  DetenerTienda(ExpandConstant('{app}'));
  Result := True;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    if MsgBox('Borrar tambien los datos de la tienda?' + #13#10#13#10 +
      'Se pierden los productos, ventas, clientes, imagenes y copias de seguridad. ' +
      'Si vas a reinstalar, contesta No y los datos se conservan.',
      mbConfirmation, MB_YESNO or MB_DEFBUTTON2) = IDYES then
      DelTree(ExpandConstant('{app}'), True, True, True);
  end;
end;
