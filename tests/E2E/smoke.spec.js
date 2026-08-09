// The trivial driven test Keel Phase 4 Step 4a asks for: the moment the first
// screen renders, the environment and the drivers stand up and ONE test passes
// against a real screen. Everything else in this directory builds on it, so when
// something breaks it matters whether this one still passes.

const { test, expect, login } = require( './fixtures' );

test( 'the playground answers and the login screen renders', async ( { page } ) => {
    const response = await page.goto( '/installer/admin/login.php' );
    expect( response.status() ).toBe( 200 );
    await expect( page.locator( 'input[name="username"]' ) ).toBeVisible();
    await expect( page.locator( 'input[name="password"]' ) ).toBeVisible();
} );

test( 'an owner can log in through the real form and reach the shell', async ( { page } ) => {
    await login( page, 'owner' );
    await expect( page.getByTestId( 'shell.brand' ) ).toBeVisible();
    await expect( page.locator( 'nav[aria-label="Main"]' ) ).toHaveCount( 1 );
} );
