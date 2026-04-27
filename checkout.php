<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="theme-color" content="#4173be" />
    <title>Checkout - Online Book Haven</title>
    <link rel="shortcut icon" type="image/webp" href="img/Logo.jpg.webp"/>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/side-nav.css">
    <link rel="stylesheet" href="css/theme.css">
    <!-- FontAwesome Icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"
            crossorigin="anonymous"></script>
    <!-- Material Design Icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/material-design-iconic-font/2.2.0/css/material-design-iconic-font.min.css">
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg py-1 navbar-dark fixed-top theme-primary-color-bg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.html">
                <img src="img/Logo.jpg.webp" alt="Logo" width="90" height="50">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarScroll">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item d-block d-sm-none">
                        <a class="nav-link" href="cart.html">Cart</a>
                    </li>
                    <li class="nav-item d-block d-sm-none">
                        <a class="nav-link" href="#">Wishlist</a>
                    </li>
                    <li class="nav-item d-block d-sm-none">
                        <a class="nav-link" href="user-profile.php">Account Settings</a>
                    </li>
                </ul>

                <div class="icon-header-item d-none d-sm-block" id="cartCount">
                    <i class="zmdi zmdi-shopping-cart text-white"></i>
                </div>

                <div class="icon-header-item mx-4 d-none d-sm-block" id="wishlistCountElm">
                    <i class="zmdi zmdi-favorite-outline text-white"></i>
                </div>

                <ul class="navbar-nav ms-auto d-none d-sm-block">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user fa-fw text-light fa-lg"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="user-profile.php">My Account</a></li>
                            <li><hr class="dropdown-divider"/></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>

            </div>
        </div>
    </nav>
</header>

<div class="py-4"></div>

<main>
    <div class="container py-4">
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>
        
        <div class="row gy-2">
            <div class="col-sm-8">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>PRICE</th>
                                    <th>QUANTITY</th>
                                    <th class="text-end">TOTAL</th>
                                </tr>
                                </thead>
                                <tbody id="cartTableBody">
                                    <tr>
                                        <td><img src="img/kigen.webp" alt="Book" style="height: 10vh;"> Book Name</td>
                                        <td class="cart-item-price">$20</td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-outline-secondary" onclick="decreaseQty()">-</button>
                                                <button class="btn btn-secondary cart-item-qty">1</button>
                                                <button class="btn btn-outline-secondary" onclick="increaseQty()">+</button>
                                            </div>
                                        </td>
                                        <td class="cart-item-subtotal text-end">$20</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <input type="text" class="form-control" placeholder="Coupon Code" id="couponCode">
                            </div>
                            <div class="col-sm-3">
                                <button class="theme-btn theme-btn-light-animated" onclick="applyCoupon()">APPLY COUPON</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body">
                        <h4>Cart Totals</h4>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td>Subtotal:</td>
                                    <td id="cartSubtotal">$20</td>
                                </tr>
                                <tr>
                                    <td>Shipping:</td>
                                    <td>
                                        <input type="text" class="form-control mb-2" id="city" placeholder="City">
                                        <input type="text" class="form-control mb-2" id="addressLine1" placeholder="Address Line 1">
                                        <input type="text" class="form-control mb-2" id="addressLine2" placeholder="Address Line 2">
                                        <input type="text" class="form-control mb-2" id="postcode" placeholder="Postcode">
                                    </td>
                                </tr>
                                <tr>
                                    <td><h5>Total:</h5></td>
                                    <td><h5 id="cartTotal">$20</h5></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="text-center">
                            <button class="theme-btn theme-btn-dark-animated" onclick="setCheckout()">CHECKOUT</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</main>

<script>
    function applyCoupon() {
        alert("Coupon applied!");
    }

    function setCheckout() {
        alert("Proceeding to checkout!");
    }

    function decreaseQty() {
        alert("Decrease quantity");
    }

    function increaseQty() {
        alert("Increase quantity");
    }
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
