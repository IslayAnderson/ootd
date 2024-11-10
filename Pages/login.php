<div class="login">
    <div class="description">
        <h1>Login</h1>
        <p>I don't know what else to say, it's the login page</p>
    </div>
    <form action="/API/user/auth.php" method="post">
        <label for="email">Email</label>
        <input type="email" placeholder="Enter Email" name="email" value="me@islayanderson.co.uk" required>

        <label for="password">Password</label>
        <input type="password" placeholder="Enter Password" name="password" required>

        <button type="submit">Login</button>
    </form>
</div>