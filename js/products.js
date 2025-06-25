function fetchAllProducts() {
    const productTable = document.getElementById("product-table");
    productTable.innerHTML = "<p>Loading products...</p>";

    fetch("fetch_products.php")
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const tableHTML = `
                    <table id="productTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Brand</th>
                                <th>Group ID</th>
                                <th>Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(product => `
                                <tr>
                                    <td>${product.id}</td>
                                    <td>${product.name}</td>
                                    <td>${product.brand ? product.brand : 'N/A'}</td>
                                    <td>${product.group_id ? product.group_id : 'N/A'}</td>
                                    <td>
                                        ${product.image_link 
                                            ? `<img src="${product.image_link}" loading="lazy" alt="${product.name}" width="50" height="50" class="product-image" style="cursor:pointer;">` 
                                            : 'No Image'}
                                    </td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                `;
                productTable.innerHTML = tableHTML;
                
                // Create modal if it doesn't already exist
                let modal = document.getElementById('imageModal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'imageModal';
                    modal.style.display = 'none';
                    modal.style.position = 'fixed';
                    modal.style.zIndex = '1000';
                    modal.style.left = '0';
                    modal.style.top = '0';
                    modal.style.width = '100%';
                    modal.style.height = '100%';
                    modal.style.overflow = 'auto';
                    modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
                    
                    // Create the image element for the modal
                    const modalImg = document.createElement('img');
                    modalImg.id = 'modalImage';
                    modalImg.style.margin = 'auto';
                    modalImg.style.display = 'block';
                    modalImg.style.maxWidth = '90%';
                    modalImg.style.maxHeight = '90%';
                    modalImg.style.marginTop = '5%';
                    
                    modal.appendChild(modalImg);
                    
                    // Close modal when clicking outside the image
                    modal.addEventListener('click', function(){
                        modal.style.display = 'none';
                    });
                    document.body.appendChild(modal);
                }
                
                // Attach event listener to each image to open the modal
                document.querySelectorAll('.product-image').forEach(img => {
                    img.addEventListener('click', () => {
                        const modalImage = document.getElementById('modalImage');
                        modalImage.src = img.src;
                        modal.style.display = 'block';
                    });
                });
            } else {
                productTable.innerHTML = "<p>No products found.</p>";
            }
        })
        .catch(error => {
            console.error("Error fetching products:", error);
            productTable.innerHTML = "<p>Error loading products. Please try again.</p>";
        });
}

window.fetchAllProducts = fetchAllProducts;
