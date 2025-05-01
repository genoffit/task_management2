document.addEventListener('DOMContentLoaded', function() {
    // Meta teqləri oxu
    const pusherKeyMeta = document.querySelector('meta[name="pusher-key"]');
    const pusherClusterMeta = document.querySelector('meta[name="pusher-cluster"]');
    const userIdMeta = document.querySelector('meta[name="user-id"]'); // İstifadəçi ID-si üçün meta
    const baseUrlMeta = document.querySelector('meta[name="base-url"]'); // Base URL üçün meta

    // Pusher üçün lazımi məlumatların mövcudluğunu yoxla
    if (!pusherKeyMeta || !pusherClusterMeta) {
        console.error('Pusher key or cluster not found in meta tags. Real-time disabled.');
        // return; // Səs hələ də işləyə bilər
    }

    const pusherKey = pusherKeyMeta ? pusherKeyMeta.getAttribute('content') : null;
    const pusherCluster = pusherClusterMeta ? pusherClusterMeta.getAttribute('content') : null;
    const userId = userIdMeta ? userIdMeta.getAttribute('content') : null; // Null ola bilər
    const baseUrl = baseUrlMeta ? baseUrlMeta.getAttribute('content') : (window.location.origin + '/task_management'); // Fallback

    // Məlumatların boş olmadığını yoxla (Pusher üçün)
    if (!pusherKey || !pusherCluster) {
        console.warn('Pusher key or cluster is empty. Real-time features might be limited.');
    }

    // === Səs faylının yolu ===
    const notificationSoundPath = baseUrl + '/assets/sounds/notification.mp3'; // Səs faylının yolu
    console.log('Trying to load sound from:', notificationSoundPath); // Yolu yoxlamaq üçün konsola yazdır
    let notificationAudio;
    try {
        notificationAudio = new Audio(notificationSoundPath);
        notificationAudio.preload = 'auto';
    } catch (e) {
        console.error("Error creating Audio object:", e);
        notificationAudio = null; // Səs işləməyəcək
    }
    // === SƏS FAYLI SONU ===

    // Pusher obyektini yarat (xəta ehtimalını nəzərə al)
    let pusher = null;
    if (pusherKey && pusherCluster) { // Yalnız key və cluster varsa başlat
        try {
             pusher = new Pusher(pusherKey, {
                cluster: pusherCluster,
                encrypted: true
            });
             // console.log('Pusher initialized.'); // Debug üçün
        } catch (error) {
            console.error('Failed to initialize Pusher:', error);
            pusher = null; // Başlatma uğursuz olarsa null təyin et
        }
    } else {
        console.warn("Pusher not initialized due to missing key or cluster.");
    }


    // === KANALLARA ABUNƏLİK (əgər pusher varsa) ===
    if (pusher) {
        // Şəxsi bildiriş kanalı (əgər istifadəçi daxil olubsa)
        if (userId) {
            const userChannelName = 'user-' + userId;
            const userChannel = pusher.subscribe(userChannelName);
            userChannel.bind('pusher:subscription_error', function(status) { console.error('Pusher user subscription failed:', status); });
            userChannel.bind('new-notification', function(data) {
                // console.log('Received new personal notification:', data); // Debug üçün
                updateNotificationUI(data);
                // === Səsi çal ===
                playNotificationSound();
                // =================
            });
        } else {
            console.warn("User ID not found, cannot subscribe to user-specific channel.");
        }

        // Tapşırıq siyahısı kanalı
        const tasksChannelName = 'tasks-channel'; // Backend-də təyin etdiyimiz kanal
        const tasksChannel = pusher.subscribe(tasksChannelName);

        tasksChannel.bind('pusher:subscription_error', function(status) {
            console.error('Pusher tasks channel subscription failed:', status);
        });
        tasksChannel.bind('pusher:subscription_succeeded', function() {
            // console.log('Successfully subscribed to tasks channel:', tasksChannelName); // Debug üçün
        });

        // Yeni tapşırıq yaradıldıqda işə düşəcək hadisə
        tasksChannel.bind('new-task-created', function(taskData) {
            // console.log('New task created event received:', taskData); // Debug üçün
            // Yalnız tasks/index səhifəsindəyiksə cədvəli yenilə
            if (document.getElementById('tasksTable')) {
                 addTaskToTable(taskData);
                 // playNotificationSound(); // İstəyə bağlı olaraq burada da səs əlavə etmək olar
            }
        });

        // Tapşırıq silindikdə işə düşəcək hadisə
        tasksChannel.bind('task-deleted', function(data) {
            // console.log('Task deleted event received:', data); // Debug üçün
            if (document.getElementById('tasksTable') && data.id) {
                removeTaskFromTable(data.id);
            }
        });

        // Tapşırıq yeniləndikdə işə düşəcək hadisə (gələcəkdə)
        // tasksChannel.bind('task-updated', function(taskData) { ... });

    } else {
        console.warn("Pusher object not available, real-time updates disabled.");
    }


    // === UI YENİLƏMƏ FUNKSİYALARI ===

    // Bildiriş dropdown-u üçün funksiya
    function updateNotificationUI(data) {
        const notificationCountElement = document.querySelector('.notification-count');
        const notificationBellElement = document.querySelector('.notification-bell');
        const notificationItemsContainer = document.querySelector('.notification-items-container');
        const noNotificationMessage = notificationItemsContainer ? notificationItemsContainer.querySelector('.dropdown-item.text-center.small.text-gray-500') : null;

        if (!notificationCountElement || !notificationBellElement || !notificationItemsContainer) {
            console.error('Notification UI elements not found.');
            return;
        }

        // 1. Sayğacı artır
        let count = parseInt(notificationCountElement.textContent) || 0;
        count++;
        notificationCountElement.textContent = count > 9 ? '9+' : count; // Düzəliş: HTML entity silindi
        notificationCountElement.style.display = 'inline-block'; // Görünən et

        // 2. Yeni bildiriş olduğunu göstər
        if (notificationBellElement) {
            notificationBellElement.classList.add('notification-received');
            setTimeout(() => { notificationBellElement.classList.remove('notification-received'); }, 1500); // Düzəliş: Arrow function sintaksisi
        }

        // 3. "Bildiriş yoxdur" mesajını gizlət
        if (noNotificationMessage && !noNotificationMessage.classList.contains('d-none')) {
            noNotificationMessage.classList.add('d-none');
        }

        // 4. Yeni bildiriş elementini yarat və siyahının başına əlavə et
        const newNotificationItem = document.createElement('a');
        newNotificationItem.classList.add('dropdown-item', 'd-flex', 'align-items-center', 'notification-item');
        newNotificationItem.href = data.link || '#';
        // Bildirişi oxunmuş kimi işarələmək üçün ID əlavə edək (əgər backend göndərirsə)
        if (data.id) {
             newNotificationItem.setAttribute('data-notification-id', data.id);
        }

        // HTML məzmunu (Düzəldilmiş)
        newNotificationItem.innerHTML = `
            <div class="me-3"><div class="icon-circle bg-primary"><i class="fas fa-bell text-white"></i></div></div>
            <div>
                <div class="small text-gray-500">${new Date().toLocaleDateString('az-AZ')} ${new Date().toLocaleTimeString('az-AZ', { hour: '2-digit', minute: '2-digit' })}</div>
                <span class="fw-bold">${data.message || 'Yeni bildiriş'}</span>
            </div>`;
        notificationItemsContainer.prepend(newNotificationItem);

        // 5. (Optional) Toastr.js
        // if (typeof toastr !== 'undefined') { toastr.info(data.message || 'Yeni bildiriş aldınız!'); }
    }


    // Tapşırıq cədvəlinə yeni sətir əlavə etmək üçün funksiya
    function addTaskToTable(taskData) {
        const tableBody = document.querySelector('#tasksTable tbody');
        const noTasksRow = document.querySelector('#tasksTable .no-tasks-row'); // Class ilə tapmaq

        if (!tableBody) {
            console.error('Task table body (#tasksTable tbody) not found.');
            return;
        }

        // "Tapşırıq yoxdur" sətrini gizlət/sil
        if (noTasksRow) {
            noTasksRow.remove();
        }

        // Yeni sətri yarat
        const newRow = document.createElement('tr');
        newRow.id = 'task-row-' + taskData.id; // Silmək üçün ID əlavə edək
        newRow.classList.add('new-task-highlight');

        // HTML məzmunu (Düzəldilmiş)
        newRow.innerHTML = `
            <td>${taskData.id}</td>
            <td>${taskData.title}</td>
            <td>${taskData.assigned_user_name}</td>
            <td>${taskData.due_date}</td>
            <td>
                <span class="text-${taskData.priority_class}">
                    ${taskData.priority_text}
                </span>
            </td>
            <td>
                <span class="badge bg-${taskData.status_badge_class}">
                    ${taskData.status_text}
                </span>
            </td>
            <td>
                <a href="${taskData.view_url}" class="btn btn-info btn-sm me-1" title="Bax">
                    <i class="fas fa-eye fa-sm"></i> Bax
                </a>
                <a href="${taskData.edit_url}" class="btn btn-warning btn-sm me-1" title="Redaktə">
                    <i class="fas fa-edit fa-sm"></i> Redaktə Et
                </a>
                <form method="POST" action="${taskData.delete_form_action}" onsubmit="return confirm('Bu tapşırığı silmək istədiyinizə əminsiniz?');" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="${taskData.csrf_token}">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" value="${taskData.id}">
                    <button type="submit" class="btn btn-danger btn-sm" title="Sil">
                        <i class="fas fa-trash fa-sm"></i> Sil
                    </button>
                </form>
            </td>
        `;

        // Yeni sətri cədvəlin başına əlavə et
        tableBody.prepend(newRow);

        // Vizuallaşdırma classını bir müddət sonra sil
        setTimeout(() => { // Düzəliş: Arrow function sintaksisi
            newRow.classList.remove('new-task-highlight');
        }, 3000);
    }

    // Tapşırıq cədvəlindən sətri silmək üçün funksiya
    function removeTaskFromTable(taskId) {
        const rowToRemove = document.getElementById('task-row-' + taskId);
        if (rowToRemove) {
            rowToRemove.style.opacity = '0'; // Solğunlaşdır
            setTimeout(() => { // Düzəliş: Arrow function sintaksisi
                rowToRemove.remove();
                // Əgər cədvəl boş qalıbsa, "Tapşırıq yoxdur" mesajını göstər
                const tableBody = document.querySelector('#tasksTable tbody');
                if (tableBody && tableBody.children.length === 0) {
                    const noTasksRow = document.createElement('tr');
                    noTasksRow.classList.add('no-tasks-row'); // Class əlavə et
                    // HTML məzmunu (Düzəldilmiş)
                    noTasksRow.innerHTML = '<td colspan="7" class="text-center text-muted">Heç bir tapşırıq tapılmadı.</td>'; // colspan sayını düzəlt (7 sütun)
                    tableBody.appendChild(noTasksRow);
                }
            }, 500); // 0.5 saniyə sonra sil
        } else {
            console.warn('Could not find task row to remove with ID:', taskId);
        }
    }

    // Tapşırıq cədvəlində sətri yeniləmək üçün funksiya (gələcəkdə)
    // function updateTaskInTable(taskData) { ... }

    // === Səs çalma funksiyası ===
    function playNotificationSound() {
        if (notificationAudio) {
            // Səsi əvvələ çək (əgər əvvəlki səs hələ bitməyibsə)
            notificationAudio.currentTime = 0;
            // Səsi çalmağa cəhd et
            const playPromise = notificationAudio.play();

            if (playPromise !== undefined) {
                playPromise.then(_ => {
                    // Səs uğurla çalındı
                    // console.log("Notification sound played.");
                })
                .catch(error => {
                    // Autoplay qadağası və ya başqa xəta
                    console.warn("Could not play notification sound (autoplay might be blocked):", error);
                });
            }
        } else {
            console.warn("Notification audio object is not available.");
        }
    }
    // ================================

    // === BİLDİRİŞƏ KLİKLƏMƏ HADİSƏSİ (Əvvəlki addımdan - aktivləşdirilməyib) ===
    // const notificationDropdownMenu = document.querySelector('.notification-dropdown-menu');
    // if (notificationDropdownMenu) {
    //     notificationDropdownMenu.addEventListener('click', function(event) {
    //         const notificationLink = event.target.closest('a.notification-item');
    //         if (notificationLink) {
    //             event.preventDefault();
    //             const notificationId = notificationLink.getAttribute('data-notification-id');
    //             const targetUrl = notificationLink.getAttribute('href');
    //             if (!notificationId || !targetUrl || targetUrl === '#') {
    //                 if (targetUrl && targetUrl !== '#') window.location.href = targetUrl;
    //                 return;
    //             }
    //             markNotificationAsRead(notificationId, function(success) {
    //                 if (success) {
    //                     notificationLink.classList.add('read');
    //                     decreaseNotificationCount();
    //                 }
    //                 window.location.href = targetUrl;
    //             });
    //         }
    //     });
    // }
    // function markNotificationAsRead(notificationId, callback) { ... } // AJAX funksiyası
    // function decreaseNotificationCount() { ... } // Sayğacı azaltma funksiyası
    // ====================================================================

}); // DOMContentLoaded sonu
