function openModal(){
    document.getElementById('itemModal').style.display = 'flex';
}

function closeModal(){
    document.getElementById('itemModal').style.display = 'none';
    document.getElementById('editModal').style.display = 'none';
    resetImageBox();
}

function editModal(itemId = null, itemImage = '', itemName = '', categoryName = '', price = '', isAvailable = 1){
    const editModal = document.getElementById('editModal');
    if (!editModal) return;

    const preview = editModal.querySelector('#image-preview');
    const uploadText = editModal.querySelector('#uploadText');
    const imageInput = editModal.querySelector('#imageInput');

    if(preview) {
        preview.src = itemImage || '';
        preview.style.display = itemImage ? 'block' : 'none';
    }

    if (uploadText) uploadText.style.display = itemImage ? 'none' : 'block';
    if (imageInput) imageInput.value = '';

    editModal.style.display = 'flex';
    
    // Populate form if item data is provided
    if (itemId !== null) {
        document.getElementById('editItemId').value = itemId;
        document.getElementById('item_name').value = itemName;
        document.getElementById('category_name').value = categoryName;
        document.getElementById('price').value = price;
        
        // Set availability radio buttons
        document.getElementById('editIsAvailableYes').checked = (isAvailable == 1);
        document.getElementById('editIsAvailableNo').checked = (isAvailable == 0);
    } else {
        // Clear form if no data provided
        document.getElementById('editItemId').value = '';
        document.getElementById('item_name').value = '';
        document.getElementById('category_name').value = '';
        document.getElementById('price').value = '';
        document.getElementById('editIsAvailableYes').checked = true; // Default to available
        document.getElementById('editIsAvailableNo').checked = false;
    }
}

window.onclick = function(event){
    let modals = document.querySelectorAll('.modal');

    modals.forEach(modal => {
        if(event.target === modal){
            modal.style.display = 'none';
        }
    })
}

function showModal(message, type = 'success'){
    Swal.fire({
        title: message,
        icon: type,
        confirmButtonText: 'OK',
        confirmButtonColor: '#3B82F6'
    })
}

function toJsLiteral(value) {
    return JSON.stringify(value == null ? '' : String(value));
}

function getItemsTableBody() {
    return document.getElementById('foodItemsTableBody') || document.querySelector('.items-table tbody');
}

function updateItemStat(statId, delta) {
    const element = document.getElementById(statId);
    if (!element) return;

    const currentValue = parseInt(element.textContent.replace(/[^0-9-]/g, ''), 10);
    const safeValue = Number.isNaN(currentValue) ? 0 : currentValue;

    element.textContent = safeValue + delta;
}

function updateItemCounters(isAvailable, delta) {
    updateItemStat('stat-total-items', delta);

    if (isAvailable) {
        updateItemStat('stat-available-items', delta);
    } else {
        updateItemStat('stat-unavailable-items', delta);
    }
}

function createEmptyItemsRow() {
    const row = document.createElement('tr');
    row.className = 'empty-state-row';
    row.innerHTML = '<td colspan="7"><div class="empty-state"><i class="fas fa-utensils"></i><p>No menu items found</p></div></td>';
    return row;
}

function removeEmptyItemsRow(tbody) {
    if (!tbody) return;

    const emptyRow = tbody.querySelector('.empty-state-row');
    if (emptyRow) {
        emptyRow.remove();
    }
}

function syncEmptyItemsState(tbody) {
    if (!tbody) return;

    const itemRows = tbody.querySelectorAll('tr[data-item-id]');
    const emptyRow = tbody.querySelector('.empty-state-row');

    if (itemRows.length === 0 && !emptyRow) {
        tbody.appendChild(createEmptyItemsRow());
    }
}

function sortItemRows(tbody) {
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr[data-item-id]'));

    rows.sort(function(a, b) {
        const aCategory = (a.dataset.category || '').toLowerCase();
        const bCategory = (b.dataset.category || '').toLowerCase();

        if (aCategory !== bCategory) {
            return aCategory.localeCompare(bCategory);
        }

        const aName = (a.dataset.itemName || '').toLowerCase();
        const bName = (b.dataset.itemName || '').toLowerCase();

        return aName.localeCompare(bName);
    });

    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}

function appendItemRow(item) {
    const tbody = getItemsTableBody();
    if (!tbody || !item) return false;

    removeEmptyItemsRow(tbody);

    const isAvailable = Number(item.is_available) === 1;
    const imageUrl = item.image_url && item.image_url.trim()
        ? item.image_url
        : '../../assets/images/food.jpg';
    const itemId = Number(item.id) || 0;
    const priceValue = Number(item.price) || 0;
    const row = document.createElement('tr');
    row.dataset.itemId = item.id;
    row.dataset.category = item.category || '';
    row.dataset.itemName = item.item_name || '';
    row.dataset.isAvailable = isAvailable ? '1' : '0';
    row.innerHTML = [
        '<td>' + AJAX.formatText(item.item_code) + '</td>',
        '<td>' + AJAX.formatText(item.item_name) + '</td>',
        '<td><img src="' + AJAX.formatText(imageUrl) + '" width="100" alt="Item image" class="imgUrl"></td>',
        '<td>&#8369;' + priceValue.toFixed(2) + '</td>',
        '<td>' + AJAX.formatText(item.category) + '</td>',
        '<td><span class="status-badge ' + (isAvailable ? 'available' : 'unavailable') + '">' + (isAvailable ? 'Available' : 'Unavailable') + '</span></td>',
        '<td><div class="item-actions"><button class="action-btn btn-edit" type="button" onclick=\'editModal(' + itemId + ', ' + toJsLiteral(imageUrl) + ', ' + toJsLiteral(item.item_name) + ', ' + toJsLiteral(item.category) + ', ' + priceValue + ', ' + (isAvailable ? '1' : '0') + ')\'>Edit</button><button type="button" class="action-btn btn-delete delete-item-btn" data-id="' + String(itemId) + '">Delete</button></div></td>'
    ].join('');

    tbody.appendChild(row);
    sortItemRows(tbody);
    updateItemCounters(isAvailable, 1);

    return true;
}

document.addEventListener('click', async function(event) {
    const deleteButton = event.target.closest('.delete-item-btn');
    if (!deleteButton) return;

    const row = deleteButton.closest('tr');
    if (!row || row.classList.contains('empty-state-row')) return;

    const nameCell = row.querySelector('td:nth-child(2)');
    const itemName = row.dataset.itemName || (nameCell ? nameCell.textContent.trim() : 'this item');

    const result = await Swal.fire({
        title: 'Delete Item?',
        text: 'Are you sure you want to delete "' + itemName + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e53e3e',
        cancelButtonColor: '#3B82F6',
        confirmButtonText: 'Yes, delete it!'
    });

    if (!result.isConfirmed) return;

    row.classList.add('updating');

    try {
        const deleteResult = await AJAX.deleteItem(deleteButton.dataset.id);

        if (deleteResult.success) {
            await Swal.fire('Deleted!', 'Item has been deleted.', 'success');

            const wasAvailable = row.dataset.isAvailable === '1';
            row.remove();
            updateItemCounters(wasAvailable, -1);
            syncEmptyItemsState(getItemsTableBody());
        } else {
            row.classList.remove('updating');
            Swal.fire('Error', deleteResult.error || 'Failed to delete item', 'error');
        }
    } catch (error) {
        row.classList.remove('updating');
        console.error('Delete error:', error);
        Swal.fire('Error', 'Network error: ' + error.message, 'error');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.querySelector('#itemModal form');

    if(addForm){
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData (this);
            const submitBtn = addForm.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            AJAX.api('add_items.php', {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.success) {
                    if (!data.item || !appendItemRow(data.item)) {
                        location.reload();
                        return;
                    }

                    showModal('Item Added Successfully', 'success');
                    closeModal();
                    this.reset();
                } else {
                    showModal(data.message || data.error || 'Failed to add item', 'error');
                }
            })
            .catch(error => {
                showModal(error.message || 'Failed to add item', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Item';
            });
        });
    }

    // Handle edit form submission
    const editForm = document.getElementById('editItemForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = editForm.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            AJAX.api('edit_items.php', {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.success) {
                    showModal('Item Updated Successfully', 'success');
                    closeModal();
                    location.reload(); // Reload to show updated item
                } else {
                    showModal(data.message || data.error || 'Failed to update item', 'error');
                }
            })
            .catch(error => {
                showModal(error.message || 'Failed to update item', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Item';
            });
        });
    }
});

