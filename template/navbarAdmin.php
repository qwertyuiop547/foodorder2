<?php
require_once '../../includes/helpers.php';

?>

<nav class="navbar">
    <div class="nav-container">
    
        <div class="header-text"> 
            <h1>Food Pulse Admin Panel</h1>
        </div>

        <ul class="nav-links">
            <p>Welcome, <?php echo $_SESSION['name'] ?>!</p>
            <form action="../../includes/logout.php" method="post">
            <input type="hidden" name="role" value="admin">
            <button type="submit">Logout</button>
            </form>
        </ul>
    </div>
</nav>

<div class="container1">
    <aside class="sidebar">

        <ul>
            <li><a href="../admin/dashboard.php">Dashboard</a></li>
            <li><a href="../admin/add_items.php">Add Items</a></li>
        </ul>
    </aside>
</div>
