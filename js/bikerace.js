// js/bikerace.js
function initBikeraceCMS() {
    const tableBody = document.getElementById('racesTable').querySelector('tbody');
    const addBtn = document.getElementById('addRowBtn');
    const saveBtn = document.getElementById('saveBtn');

    // add/delete wiring…
    tableBody.addEventListener('click', e => {
        if (e.target.classList.contains('deleteBtn'))
            e.target.closest('tr').remove();
    });

    addBtn.addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
    <td><input class="date"  placeholder="DD.MM.YY"></td>
    <td class="race-cell">
      <input class="race"     placeholder="Løbsnavn">
      <input class="raceLink" placeholder="Link til løb (https://…)">
    </td>
    <td><input class="city"   placeholder="By"></td>
    <td><input class="type"   placeholder="Type"></td>
    <td><button class="deleteBtn action-btn">−</button></td>
  `;
        tableBody.appendChild(tr);
    });


    saveBtn.addEventListener('click', async () => {
        const data = Array.from(tableBody.querySelectorAll('tr')).map(tr => {
            const [d, r, link, c, t] = tr.querySelectorAll('input');
            return {
                date: d.value,
                race: r.value,
                link: link.value,    // ← new field
                city: c.value,
                type: t.value
            };
        });

        try {
            const res = await fetch('/bikerace.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            alert(result.status === 'success' ? 'Gemt!' : 'Fejl: ' + result.message);
        } catch {
            alert('Kunne ikke gemme ændringer');
        }
    });
}

window.initBikeraceCMS = initBikeraceCMS;
