// File: js/product_creation.js

document.addEventListener("DOMContentLoaded", () => {
  // Felter fra formularen
  const navnInput       = document.getElementById("produktnavn");
  const nummerInput     = document.getElementById("produktnummer");
  const eanInput        = document.getElementById("ean");
  const prisInput       = document.getElementById("pris");
  const beskrivelseInput= document.getElementById("beskrivelse");

  // De "ægte" felter, som vi til sidst sender til create_product.php
  const kortBeskrivInput     = document.getElementById("kortBeskrivelse");
  const udvidetBeskrivInput  = document.getElementById("udvidetBeskrivelse");
  const titleTagInput        = document.getElementById("titleTag");
  const metaTagBeskrivInput  = document.getElementById("metaTagBeskrivelse");

  // CSV‐upload
  const csvInput = document.getElementById("productCSV");

  // Generate‐knap og AI‐preview‐container + felter
  const generateBtn     = document.getElementById("generateDescriptionBtn");
  const aiPreviewDiv    = document.getElementById("aiPreviewContainer");
  const aiKortArea      = document.getElementById("aiKort");
  const aiUdvidetArea   = document.getElementById("aiUdvidet");
  const aiTitleTagInput = document.getElementById("aiTitleTag");
  const aiMetaTagArea   = document.getElementById("aiMetaTag");

  // Det reelle form-element
  const form = document.getElementById("productCreationForm");

  // Hjælpefunktion: Læs en File‐objekt som UTF-8‐tekst
  function readFileAsText(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(reader.error);
      reader.readAsText(file);
    });
  }

  // Når brugeren klikker "Generer via AI"
  generateBtn.addEventListener("click", async () => {
    // 1) Saml værdier
    const produktnavn    = navnInput.value.trim();
    const produktnummer  = nummerInput.value.trim();
    const ean            = eanInput.value.trim();
    const pris           = prisInput.value.trim();
    const beskrivManual  = beskrivelseInput.value.trim();

    // Tjek at de obligatoriske felter er udfyldt
    if (!produktnavn || !produktnummer || !pris) {
      alert("Udfyld venligst Produktnavn, Produktnummer og Pris, før du genererer beskrivelse.");
      return;
    }

    // 2) Læs CSV‐indhold (hvis valgt)
    let csvText = "";
    if (csvInput.files.length > 0) {
      try {
        csvText = await readFileAsText(csvInput.files[0]);
      } catch (err) {
        console.error("CSV‐læsning fejlede:", err);
        alert("Kunne ikke læse CSV‐filen. Tjek filen og prøv igen.");
        return;
      }
    }

    // 3) Deaktiver knap mens vi venter på AI‐svar
    generateBtn.disabled = true;
    generateBtn.textContent = "Genererer…";

    // 4) Lav et JSON‐payload til PHP
    const payload = {
      produktnavn,
      produktnummer,
      ean,
      pris,
      beskriv_manual: beskrivManual,
      csv_data: csvText
    };

    try {
      const response = await fetch("generate_description.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      if (!response.ok) {
        const text = await response.text();
        throw new Error(text || "Serverfejl ved generering");
      }
      const data = await response.json();
      if (data.error) {
        throw new Error(data.error);
      }

      // 5) Udfyld AI‐felterne og vis preview‐boksen
      aiKortArea.value      = data.kort_beskrivelse;
      aiUdvidetArea.value   = data.udvidet_beskrivelse;
      aiTitleTagInput.value = data.title_tag;
      aiMetaTagArea.value   = data.metatag_beskrivelse;
      aiPreviewDiv.style.display = "block";
    }
    catch (err) {
      console.error("Fejl ved AI‐generering:", err);
      alert("Kunne ikke generere beskrivelse: " + err.message);
    }
    finally {
      generateBtn.disabled = false;
      generateBtn.textContent = "Generer via AI";
    }
  });

  // Når formularen sendes: Kopiér AI‐felter ind i de reelle felter
  form.addEventListener("submit", (e) => {
    // Hvis AI‐preview‐boksen er synlig, brug dens indhold
    if (aiPreviewDiv.style.display === "block") {
      kortBeskrivInput.value    = aiKortArea.value.trim();
      udvidetBeskrivInput.value = aiUdvidetArea.value.trim();
      titleTagInput.value       = aiTitleTagInput.value.trim();
      metaTagBeskrivInput.value = aiMetaTagArea.value.trim();
    }
    // Formularen fortsætter med sin normale submission (f.eks. til create_product.php)
  });
});
