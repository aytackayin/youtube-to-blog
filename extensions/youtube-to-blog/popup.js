document.addEventListener('DOMContentLoaded', async () => {
    const titleInput = document.getElementById('title');
    const categorySearch = document.getElementById('categorySearch');
    const categoryTree = document.getElementById('categoryTree');
    const noteInput = document.getElementById('note');
    const btnSave = document.getElementById('btnSave');
    const statusDiv = document.getElementById('status');
    const addToAttachments = document.getElementById('addToAttachments');

    const settingsPanel = document.getElementById('settingsPanel');
    const toggleSettings = document.getElementById('toggleSettings');
    const siteUrlInput = document.getElementById('siteUrl');
    const apiKeyInput = document.getElementById('apiKey');
    const saveSettingsBtn = document.getElementById('saveSettings');

    // New Category Elements
    const toggleNewCategory = document.getElementById('toggleNewCategory');
    const newCategoryForm = document.getElementById('newCategoryForm');
    const newCategoryTitle = document.getElementById('newCategoryTitle');
    const btnCreateCategory = document.getElementById('btnCreateCategory');

    let youtubeData = { id: '', description: '' };
    let fullCategoryData = []; // Store original data for filtering

    // 1. Load Settings
    const data = await chrome.storage.local.get(['siteUrl', 'apiKey']);
    if (data.siteUrl) siteUrlInput.value = data.siteUrl;
    if (data.apiKey) apiKeyInput.value = data.apiKey;

    // 2. Hide/Show Settings
    toggleSettings.addEventListener('click', () => {
        settingsPanel.style.display = settingsPanel.style.display === 'block' ? 'none' : 'block';
    });

    saveSettingsBtn.addEventListener('click', async () => {
        let url = siteUrlInput.value.trim().replace(/\/$/, "");
        await chrome.storage.local.set({ siteUrl: url, apiKey: apiKeyInput.value.trim() });
        alert('Ayarlar kaydedildi!');
        fetchCategories();
        settingsPanel.style.display = 'none';
    });

    // 3. Detect YouTube Info
    chrome.tabs.query({ active: true, currentWindow: true }, async (tabs) => {
        const tab = tabs[0];
        if (tab && tab.url && tab.url.includes("youtube.com/watch?v=")) {
            const urlObj = new URL(tab.url);
            youtubeData.id = urlObj.searchParams.get("v");

            try {
                const results = await chrome.scripting.executeScript({
                    target: { tabId: tab.id },
                    func: () => {
                        const titleEl = document.querySelector('h1.ytd-watch-metadata yt-formatted-string') ||
                            document.querySelector('h1.style-scope.ytd-video-primary-info-renderer');

                        const descEl = document.querySelector('#description-inline-expander .yt-core-attributed-string') ||
                            document.querySelector('yt-attributed-string#description-text') ||
                            document.querySelector('#description .content');

                        let description = "";
                        if (descEl) {
                            const clone = descEl.cloneNode(true);
                            clone.querySelectorAll('a').forEach(link => {
                                let href = link.getAttribute('href');
                                if (href && !href.startsWith('/hashtag/') && !href.startsWith('hashtag/')) {
                                    if (href.includes('youtube.com/redirect') || href.startsWith('/redirect')) {
                                        try {
                                            const urlParams = new URLSearchParams(href.split('?')[1]);
                                            href = urlParams.get('q') || href;
                                        } catch (e) { }
                                    }
                                    link.innerText = href;
                                }
                            });
                            description = clone.innerText;
                        }

                        return {
                            title: titleEl ? titleEl.innerText : document.title.replace(" - YouTube", ""),
                            description: description
                        };
                    }
                });

                if (results?.[0]?.result) {
                    const info = results[0].result;
                    titleInput.value = info.title;
                    youtubeData.description = info.description;
                }
            } catch (e) {
                titleInput.value = tab.title.replace(" - YouTube", "");
            }
        } else {
            showStatus("Lütfen bir YouTube video sayfasında olun.", "error");
            btnSave.disabled = true;
        }
    });

    // 4. Category Tree Logic
    async function fetchCategories() {
        const { siteUrl, apiKey } = await chrome.storage.local.get(['siteUrl', 'apiKey']);
        if (!siteUrl || !apiKey) {
            categoryTree.innerHTML = '<span style="color: #f87171;">Önce ayarları yapın!</span>';
            return;
        }

        try {
            const response = await fetch(`${siteUrl}/api/youtube/categories`, {
                headers: { 'X-API-KEY': apiKey, 'Accept': 'application/json' }
            });
            fullCategoryData = await response.json();
            renderCategoryTree(fullCategoryData);
        } catch (err) {
            categoryTree.innerHTML = '<span style="color: #f87171;">Bağlantı hatası!</span>';
        }
    }

    function renderCategoryTree(data, searchTerm = '') {
        categoryTree.innerHTML = '';
        const searchLower = searchTerm.toLowerCase();

        function createNodeElement(node, level = 0) {
            const hasChildren = node.children && node.children.length > 0;
            const matchesSearch = node.title.toLowerCase().includes(searchLower);

            // If searching, we skip the tree structure and show matches flatly or recursively
            // But user wants a searchable tree. Simple approach: if a child matches, show parent.
            let childElements = [];
            let anyChildMatches = false;

            if (hasChildren) {
                node.children.forEach(child => {
                    const { element, matches } = createNodeElement(child, level + 1);
                    childElements.push(element);
                    if (matches) anyChildMatches = true;
                });
            }

            if (searchTerm && !matchesSearch && !anyChildMatches) {
                return { element: null, matches: false };
            }

            const itemWrap = document.createElement('div');
            itemWrap.className = 'tree-node-wrap';
            if (searchTerm) itemWrap.setAttribute('data-open', 'true');

            const item = document.createElement('div');
            item.className = 'tree-item';
            item.style.paddingLeft = `${level * 16}px`;

            // Toggle arrow
            const toggle = document.createElement('span');
            toggle.className = `tree-toggle ${hasChildren ? '' : 'hidden'}`;
            toggle.innerHTML = '▼';
            if (hasChildren) {
                toggle.onclick = (e) => {
                    e.stopPropagation();
                    const group = itemWrap.querySelector('.tree-children');
                    if (group) {
                        const isClosed = group.style.display === 'none';
                        group.style.display = isClosed ? 'block' : 'none';
                        toggle.classList.toggle('collapsed', !isClosed);
                    }
                };
            }
            item.appendChild(toggle);

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'checkbox-custom cat-checkbox';
            checkbox.value = node.id;
            checkbox.id = `cat-${node.id}`;

            const label = document.createElement('label');
            label.setAttribute('for', `cat-${node.id}`);
            label.innerText = node.title;
            if (searchTerm && matchesSearch) {
                label.style.color = 'var(--primary)';
                label.style.fontWeight = 'bold';
            }

            item.appendChild(checkbox);
            item.appendChild(label);
            itemWrap.appendChild(item);

            if (hasChildren) {
                const childrenGroup = document.createElement('div');
                childrenGroup.className = 'tree-children';
                childElements.forEach(el => el && childrenGroup.appendChild(el));
                // Collapse by default unless searching
                if (!searchTerm) {
                    childrenGroup.style.display = 'none';
                    toggle.classList.add('collapsed');
                }
                itemWrap.appendChild(childrenGroup);
            }

            return { element: itemWrap, matches: matchesSearch || anyChildMatches };
        }

        data.forEach(node => {
            const { element } = createNodeElement(node);
            if (element) categoryTree.appendChild(element);
        });

        if (categoryTree.innerHTML === '') {
            categoryTree.innerHTML = '<span style="color: #94a3b8; font-size: 12px; padding: 8px;">Sonuç bulunamadı.</span>';
        }
    }

    categorySearch.addEventListener('input', (e) => {
        renderCategoryTree(fullCategoryData, e.target.value);
    });

    // 5. New Category Creation
    toggleNewCategory.addEventListener('click', () => {
        const isVisible = newCategoryForm.style.display === 'flex';
        newCategoryForm.style.display = isVisible ? 'none' : 'flex';
        toggleNewCategory.innerText = isVisible ? '+ Yeni Kategori Ekle' : ' Vazgeç';
        if (!isVisible) newCategoryTitle.focus();
    });

    btnCreateCategory.addEventListener('click', async () => {
        const title = newCategoryTitle.value.trim();
        if (!title) return;

        // Find the first selected category to use as parent
        const selectedCheckbox = document.querySelector('.cat-checkbox:checked');
        const parentId = selectedCheckbox ? selectedCheckbox.value : null;

        const { siteUrl, apiKey } = await chrome.storage.local.get(['siteUrl', 'apiKey']);
        btnCreateCategory.disabled = true;

        try {
            const response = await fetch(`${siteUrl}/api/youtube/categories`, {
                method: 'POST',
                headers: {
                    'X-API-KEY': apiKey,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    title: title,
                    parent_id: parentId
                })
            });

            if (response.ok) {
                newCategoryTitle.value = '';
                newCategoryForm.style.display = 'none';
                toggleNewCategory.innerText = '+ Yeni Kategori Ekle';
                fetchCategories(); // Refresh tree
            } else {
                const res = await response.json();
                alert(res.message || "Hata oluştu.");
            }
        } catch (err) {
            alert("Bağlantı hatası!");
        } finally {
            btnCreateCategory.disabled = false;
        }
    });

    // 6. Send to CMS (Now handled via Queue in next section)

    function showStatus(msg, type) {
        statusDiv.textContent = msg;
        statusDiv.className = `status ${type}`;
        statusDiv.style.display = 'block';
        if (type === 'success') {
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 3000);
        }
    }

    // 7. Queue Management
    // 7. Queue Management (Persistent)
    const progressList = document.createElement('div');
    progressList.id = 'progressList';
    progressList.style.marginTop = '15px';
    progressList.style.borderTop = '1px solid var(--border)';
    progressList.style.paddingTop = '10px';
    document.getElementById('mainForm').appendChild(progressList);

    // Spinner Style
    if (!document.getElementById('spinner-style')) {
        const style = document.createElement('style');
        style.id = 'spinner-style';
        style.innerHTML = `
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .spinner { display: inline-block; animation: spin 2s linear infinite; }
            .status-pending { color: var(--primary); }
            .status-success { color: #10b981; }
            .status-error { color: #ef4444; }
        `;
        document.head.appendChild(style);
    }

    function renderQueueItem(videoId, task) {
        let item = document.getElementById(`queue-${videoId}`);
        if (!item) {
            item = document.createElement('div');
            item.id = `queue-${videoId}`;
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.justifyContent = 'space-between';
            item.style.padding = '8px';
            item.style.marginBottom = '6px';
            item.style.background = 'rgba(255, 255, 255, 0.05)';
            item.style.borderRadius = '6px';
            item.style.fontSize = '12px';
            progressList.appendChild(item);
        }

        const title = task.title;
        let statusHtml = '';

        if (task.status === 'pending') {
            statusHtml = '<span class="status-pending"><span class="spinner">⏳</span> Kaydediliyor...</span>';
        } else if (task.status === 'success') {
            statusHtml = '<span class="status-success">✓ Tamamlandı</span>';
        } else {
            statusHtml = `<span class="status-error">✕ ${task.message || 'Hata'}</span>`;
        }

        item.innerHTML = `
            <span style="flex: 1; margin-right: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                ${title}
            </span>
            <span style="font-weight: bold;">${statusHtml}</span>
        `;
    }

    async function updateQueueUI() {
        const { taskQueue } = await chrome.storage.local.get(['taskQueue']);
        progressList.innerHTML = '';
        if (taskQueue) {
            Object.keys(taskQueue).forEach(videoId => {
                renderQueueItem(videoId, taskQueue[videoId]);
            });
        }
    }

    // Listen for storage changes to update UI automatically
    chrome.storage.onChanged.addListener((changes, namespace) => {
        if (namespace === 'local' && changes.taskQueue) {
            updateQueueUI();
        }
    });

    // Initial load
    updateQueueUI();

    btnSave.addEventListener('click', async () => {
        const selectedCheckboxes = document.querySelectorAll('.cat-checkbox:checked');
        const categoryIds = Array.from(selectedCheckboxes).map(cb => cb.value);

        if (categoryIds.length === 0) return showStatus("Lütfen en az bir kategori seçin.", "error");

        const videoId = youtubeData.id;
        const videoTitle = titleInput.value;
        const description = youtubeData.description || "Açıklama bulunamadı.";
        const note = noteInput.value;
        const attachments = addToAttachments.checked;

        // Send to background
        chrome.runtime.sendMessage({
            action: 'saveBlog',
            data: {
                title: videoTitle,
                video_id: videoId,
                description: description,
                category_ids: categoryIds,
                note: note,
                add_to_attachments: attachments
            }
        });

        // Optimistic UI update (optional, usually storage listener handles it almost instantly)
        showStatus("Kuyruğa eklendi. Arka planda işleniyor...", "success");
    });

    fetchCategories();
});
