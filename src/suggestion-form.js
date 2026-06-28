document.addEventListener('DOMContentLoaded', function() {
    const parkSelect = document.getElementById('dogpark-park');
    const newParkFields = document.getElementById('dogpark-new-park-fields');

    if (parkSelect) {
        parkSelect.addEventListener('change', function() {
            newParkFields.style.display = this.value === 'new' ? 'block' : 'none';
        });
    }

    const form = document.getElementById('dogpark-suggestion');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const data = {
                park_id: formData.get('park_id'),
                name: formData.get('name'),
                address: formData.get('address'),
                shade: formData.get('shade'),
                water: formData.get('water') ? 1 : 0,
                drainage: formData.get('drainage'),
                lighting: formData.get('lighting'),
                email: formData.get('email'),
                notes: formData.get('notes'),
                dogpark_hp: formData.get('dogpark_hp'),
                dogpark_suggest_nonce: formData.get('dogpark_suggest_nonce')
            };

            fetch(dogparkFormSettings.restUrl + 'suggestions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': dogparkFormSettings.nonce
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success || result.data?.message === 'ok') {
                    form.style.display = 'none';
                    document.getElementById('dogpark-submission-message').style.display = 'block';
                } else if (result.data && result.data.message) {
                    alert(result.data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }
});