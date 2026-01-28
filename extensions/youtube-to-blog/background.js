
// Queue management in background
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'saveBlog') {
        processQueueItem(request.data);
        sendResponse({ status: 'queued' });
    }
});

async function processQueueItem(data) {
    // 1. Durumu 'pending' olarak kaydet
    await updateQueueStatus(data.video_id, data.title, 'pending');

    const { siteUrl, apiKey } = await chrome.storage.local.get(['siteUrl', 'apiKey']);

    if (!siteUrl || !apiKey) {
        await updateQueueStatus(data.video_id, data.title, 'error', 'Ayarlar eksik!');
        return;
    }

    try {
        const response = await fetch(`${siteUrl}/api/youtube/store`, {
            method: 'POST',
            headers: {
                'X-API-KEY': apiKey,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok) {
            // Başarılı kayıttan sonra durum (polling) takibi başlat
            await updateQueueStatus(data.video_id, data.title, 'pending', 'Video işleniyor...');

            // Eğer blog_id döndüyse polling başlat
            if (result.blog_id) {
                pollStatus(result.blog_id, data.video_id, data.title, siteUrl, apiKey);
            } else {
                await updateQueueStatus(data.video_id, data.title, 'success', 'Kaydedildi (Video yok)');
                setTimeout(() => removeQueueItem(data.video_id), 5000);
            }
        } else {
            await updateQueueStatus(data.video_id, data.title, 'error', result.message || 'Hata oluştu');
        }
    } catch (err) {
        await updateQueueStatus(data.video_id, data.title, 'error', 'Sunucu hatası');
    }
}

async function pollStatus(blogId, videoId, title, siteUrl, apiKey) {
    const maxAttempts = 720; // 1 saat (5 saniyede 1 kontrol * 720 = 3600 sn)
    let attempts = 0;

    const intervalId = setInterval(async () => {
        attempts++;
        try {
            const response = await fetch(`${siteUrl}/api/youtube/status/${blogId}`, {
                method: 'GET',
                headers: { 'X-API-KEY': apiKey, 'Accept': 'application/json' }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.status === 'completed') {
                    clearInterval(intervalId);
                    await updateQueueStatus(videoId, title, 'success', 'Tamamlandı');
                    setTimeout(() => removeQueueItem(videoId), 5000);
                } else {
                    // Hala işlemde, belki mesajı güncelleyebiliriz
                    // await updateQueueStatus(videoId, title, 'pending', 'Video işleniyor... (' + attempts + ')');
                }
            } else {
                if (response.status === 404) {
                    // Blog silinmiş olabilir
                    clearInterval(intervalId);
                    await updateQueueStatus(videoId, title, 'error', 'Blog bulunamadı (404)');
                }
            }
        } catch (e) {
            // Bağlantı hatası vs. (geçici olabilir, devam et)
        }

        if (attempts >= maxAttempts) {
            clearInterval(intervalId);
            await updateQueueStatus(videoId, title, 'error', 'Zaman aşımı (İşlem çok uzun sürdü)');
        }
    }, 5000); // 5 saniyede bir kontrol
}

async function updateQueueStatus(videoId, title, status, message = '') {
    const { taskQueue } = await chrome.storage.local.get(['taskQueue']) || { taskQueue: {} };
    const queue = taskQueue || {};

    queue[videoId] = {
        title: title,
        status: status,
        message: message,
        timestamp: Date.now()
    };

    await chrome.storage.local.set({ taskQueue: queue });
}

async function removeQueueItem(videoId) {
    const { taskQueue } = await chrome.storage.local.get(['taskQueue']);
    if (taskQueue && taskQueue[videoId]) {
        delete taskQueue[videoId];
        await chrome.storage.local.set({ taskQueue });
    }
}
