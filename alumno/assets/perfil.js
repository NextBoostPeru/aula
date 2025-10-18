(function(){
  const form = $('#profileForm');
  if (!form) return;

  page.clearActions();
  page.setTitle('Perfil');

  const msg = $('#profileMsg');
  const avatarInput = $('#avatarInput');
  const avatarPreview = $('#avatarPreview');
  const btnSave = $('#btnSaveProfile');
  const PLACEHOLDER = 'https://via.placeholder.com/80x80?text=Avatar';

  const setMessage = (type, text) => {
    if (!msg) return;
    const base = 'text-sm mt-2';
    let color = ' text-gray-600';
    if (type === 'error') color = ' text-red-700';
    else if (type === 'success') color = ' text-green-700';
    else if (type === 'info') color = ' text-gray-600';
    msg.className = base + color;
    msg.textContent = text || '';
  };

  const toggleDisabled = (disabled) => {
    form.querySelectorAll('input, button').forEach((el) => {
      if (el === avatarInput) return; // allow selecting avatar while loading
      el.disabled = !!disabled;
    });
    if (btnSave) btnSave.disabled = !!disabled;
  };

  const fillProfile = (user) => {
    $('#profileName').value = user?.name ?? '';
    $('#profileEmail').value = user?.email ?? '';
    $('#profilePhone').value = user?.phone ?? '';
    $('#profileDni').value = user?.dni ?? '';
    if (avatarPreview) {
      avatarPreview.src = user?.avatar_url ? user.avatar_url : PLACEHOLDER;
    }
    form.querySelectorAll('input[type="password"]').forEach((input) => {
      input.value = '';
    });
  };

  const loadProfile = async () => {
    toggleDisabled(true);
    setMessage('info', 'Cargando datos del perfil...');
    try {
      const resp = await studentApi('profile_get.php');
      if (!resp?.ok) throw new Error(resp?.msg || 'No se pudo obtener el perfil.');
      fillProfile(resp.user || {});
      setMessage('', '');
    } catch (error) {
      console.error('No se pudo cargar el perfil', error);
      setMessage('error', error.message || 'No se pudo cargar el perfil.');
    } finally {
      toggleDisabled(false);
    }
  };

  avatarInput?.addEventListener('change', (event) => {
    const file = event.target.files?.[0];
    if (!file || !avatarPreview) return;
    avatarPreview.src = URL.createObjectURL(file);
  });

  btnSave?.addEventListener('click', async () => {
    const fd = new FormData(form);
    toggleDisabled(true);
    setMessage('info', 'Guardando cambios...');
    try {
      const resp = await studentApi('profile_update.php', { method: 'POST', body: fd });
      if (!resp?.ok) throw new Error(resp?.msg || 'No se pudo actualizar el perfil.');
      if (resp.avatar_url && avatarPreview) {
        avatarPreview.src = resp.avatar_url;
      }
      await loadProfile();
      setMessage('success', resp.msg || 'Perfil actualizado');
    } catch (error) {
      console.error('No se pudo actualizar el perfil', error);
      setMessage('error', error.message || 'No se pudo actualizar el perfil.');
    } finally {
      toggleDisabled(false);
    }
  });

  loadProfile();
})();
