<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameTopUp - Top Up Games</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <div class="logo">🎮 TOPZONE</div>
                <div class="user-info">
                    <span id="userStatus">Selamat Datang!</span>
                    <a href="admin.php" class="btn" style="background: #64748b; color: white; font-size: 12px; padding: 5px 10px;">Admin Panel</a>
                </div>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>🔥 Top Up Games Termurah</h1>
            <p>Robux • ML • FF • Genshin - Instant Delivery</p>
        </div>
    </section>

    <section class="products-section">
        <div class="container">
            <div class="products-grid">
                
                <div class="product-card" onclick="location.href='game_detail.php?game=roblox'">
                    <div class="product-image" style="background-image: url('Roblox.jpg')">
                        </div>
                    <h3>Roblox</h3>
                    <div class="meta">
                        <div class="rating">4.9 ⭐</div>
                        <div class="sold">100K+ terjual</div>
                    </div>
                    <div class="price">Rp 15.000 <span>mulai</span></div>
                </div>

                <div class="product-card" onclick="location.href='game_detail.php?game=mlbb'">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1570549717069-33bed2ebafec?w=400&h=200')">
                        <span>⚔️</span>
                    </div>
                    <h3>Mobile Legends</h3>
                    <div class="meta">
                        <div class="rating">4.8 ⭐</div>
                        <div class="sold">85K+ terjual</div>
                    </div>
                    <div class="price">Rp 45.000 <span>mulai</span></div>
                </div>

                <div class="product-card" onclick="location.href='game_detail.php?game=freefire'">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1611606066920-63f3939b7b9e?w=400&h=200')">
                        <span>🔥</span>
                    </div>
                    <h3>Free Fire</h3>
                    <div class="meta">
                        <div class="rating">4.9 ⭐</div>
                        <div class="sold">45K+ terjual</div>
                    </div>
                    <div class="price">Rp 16.000 <span>mulai</span></div>
                </div>

                <div class="product-card" onclick="location.href='game_detail.php?game=genshin'">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1624565881807-406d72c312af?w=400&h=200')">
                        <span>⭐</span>
                    </div>
                    <h3>Genshin Impact</h3>
                    <div class="meta">
                        <div class="rating">4.7 ⭐</div>
                        <div class="sold">23K+ terjual</div>
                    </div>
                    <div class="price">Rp 20.000 <span>mulai</span></div>
                </div>

            </div>
        </div>
    </section>

    <script src="javascript.js"></script>
</body>
</html>