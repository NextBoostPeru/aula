<div class="bg-white rounded-2xl shadow p-4 space-y-6">
  <form id="profileForm" class="grid md:grid-cols-2 gap-4" autocomplete="off">
    <div>
      <label class="text-sm text-gray-600" for="profileName">Nombre</label>
      <input id="profileName" name="name" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="name">
    </div>
    <div>
      <label class="text-sm text-gray-600" for="profileEmail">Correo</label>
      <input id="profileEmail" name="email" type="email" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="email">
    </div>
    <div>
      <label class="text-sm text-gray-600" for="profilePhone">Celular</label>
      <input id="profilePhone" name="phone" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="tel">
    </div>
    <div>
      <label class="text-sm text-gray-600" for="profileDni">DNI</label>
      <input id="profileDni" name="dni" type="text" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="off">
    </div>
    <div class="md:col-span-2">
      <label class="text-sm text-gray-600" for="avatarInput">Foto de perfil</label>
      <div class="mt-1 flex items-center gap-3">
        <img id="avatarPreview" src="https://via.placeholder.com/80x80?text=Avatar" alt="Avatar" class="w-16 h-16 rounded-xl object-cover border">
        <input id="avatarInput" name="avatar" type="file" accept="image/*" class="text-sm">
      </div>
      <p class="text-xs text-gray-500 mt-1">Formatos permitidos: JPG, PNG, WebP (máx. 2MB).</p>
    </div>
    <div class="md:col-span-2 grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-sm text-gray-600" for="currentPassword">Contraseña actual</label>
        <input id="currentPassword" name="current_password" type="password" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="current-password">
      </div>
      <div>
        <label class="text-sm text-gray-600" for="newPassword">Nueva contraseña</label>
        <input id="newPassword" name="new_password" type="password" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="new-password">
      </div>
      <div>
        <label class="text-sm text-gray-600" for="newPasswordConfirm">Repetir nueva contraseña</label>
        <input id="newPasswordConfirm" name="new_password_confirm" type="password" class="w-full mt-1 px-3 py-2 border rounded-xl" autocomplete="new-password">
      </div>
    </div>
    <div class="md:col-span-2 flex items-center justify-end gap-2">
      <button type="button" id="btnSaveProfile" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white">
        <i data-feather="save"></i> Guardar cambios
      </button>
    </div>
  </form>
  <div id="profileMsg" class="text-sm"></div>
</div>
