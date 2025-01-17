<?php ?>
<div class="signup">
    <form action="/API/user/create.php" method="post">
        <div class="description">
            <h1>You’re In!</h1>
            <p>Complete your signup to get access to OOTD, get ready for daily style inspiration and fresh outfit ideas. We’re excited to have you on board!</p>
        </div>
        <div class="name">
            <label for="first_name">First Name <br>
                <input type="text" placeholder="Enter First name" name="first_name" required>
            </label>

            <label for="last_name">Last Name <br>
                <input type="text" placeholder="Enter Last name" name="last_name" required>
            </label>
        </div>

        <label for="email">Email</label>
        <input type="email" placeholder="Enter email" name="email" required>

        <label for="username">Username</label>
        <input type="text" placeholder="Enter Username" name="username" required>

        <label for="password">Password</label>
        <input type="password" placeholder="Enter Password" name="password" required>

        <button type="submit">Complete signup</button>

    </form>
</div>