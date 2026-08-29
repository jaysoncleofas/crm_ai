import { expect, test } from '@playwright/test'

const ADMIN = { email: 'admin@crm.test', password: 'password' }

async function signIn(page, user = ADMIN) {
  await page.goto('/login')
  await page.getByLabel('Email').fill(user.email)
  await page.getByLabel('Password').fill(user.password)
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { name: /Good to see you/ })).toBeVisible()
}

test('the SPA mounts and shows the sign-in screen', async ({ page }) => {
  await page.goto('/login')

  await expect(page.getByRole('heading', { name: 'Sign in to Jayson CRM' })).toBeVisible()
  await expect(page.locator('#app')).not.toBeEmpty()
})

test('an unauthenticated deep link redirects to sign-in', async ({ page }) => {
  await page.goto('/contacts')

  await expect(page).toHaveURL(/\/login/)
})

test('signing in lands on a dashboard with real figures', async ({ page }) => {
  await signIn(page)

  await expect(page.getByText('Open pipeline')).toBeVisible()
  await expect(page.getByText('Pipeline by stage')).toBeVisible()

  // The seeded dataset must produce a non-zero pipeline value.
  const openPipeline = page.locator('div').filter({ hasText: /^Open pipeline/ }).first()
  await expect(openPipeline).not.toContainText('$0')
})

test('contacts list paginates, searches and opens a detail view', async ({ page }) => {
  await signIn(page)

  await page.getByRole('link', { name: 'Contacts' }).click()
  await expect(page.getByRole('heading', { name: 'Contacts' })).toBeVisible()
  await expect(page.locator('tbody tr').first()).toBeVisible()

  const name = await page.locator('tbody tr td a').first().innerText()

  await page.getByPlaceholder('Search name, email, phone…').fill(name.split(' ')[0])
  await expect(page.locator('tbody tr').first()).toContainText(name.split(' ')[0])

  await page.locator('tbody tr td a').first().click()
  await expect(page.getByRole('heading', { level: 1 })).toContainText(name.split(' ')[0])
  await expect(page.getByText('Record history')).toBeVisible()
})

test('a contact can be created and then soft deleted', async ({ page }) => {
  await signIn(page)
  await page.goto('/contacts')

  const stamp = Date.now()
  await page.getByRole('button', { name: 'New contact' }).click()
  await page.getByLabel('First name').fill('E2E')
  await page.getByLabel('Last name').fill(`Probe${stamp}`)
  await page.getByLabel('Email', { exact: true }).fill(`e2e.${stamp}@example.test`)
  await page.getByRole('button', { name: 'Create contact' }).click()

  await expect(page.getByText('Contact created.')).toBeVisible()

  await page.getByPlaceholder('Search name, email, phone…').fill(`Probe${stamp}`)
  const row = page.locator('tbody tr').filter({ hasText: `Probe${stamp}` })
  await expect(row).toHaveCount(1)

  await row.getByRole('button', { name: 'Delete' }).click()
  await page.getByRole('button', { name: 'Move to trash' }).click()
  await expect(page.getByText('Contact moved to trash.')).toBeVisible()

  // Gone from the default view, still present under "Deleted only".
  await expect(page.locator('tbody tr').filter({ hasText: `Probe${stamp}` })).toHaveCount(0)
  await page.getByLabel('Filter by deleted state').selectOption('only')
  await expect(page.locator('tbody tr').filter({ hasText: `Probe${stamp}` })).toHaveCount(1)
})

test('the pipeline board renders stage columns with deals', async ({ page }) => {
  await signIn(page)

  await page.getByRole('link', { name: 'Pipeline' }).click()
  await expect(page.getByRole('heading', { name: 'Pipeline' })).toBeVisible()
  await expect(page.getByText('Qualification')).toBeVisible()
  await expect(page.getByText('Closed Won')).toBeVisible()
})

test('the audit log shows who changed what', async ({ page }) => {
  await signIn(page)

  await page.getByRole('link', { name: 'Audit log' }).click()
  await expect(page.getByRole('heading', { name: 'Audit log' })).toBeVisible()
  await expect(page.getByText('was created').first()).toBeVisible()
})

test('a viewer cannot see write controls', async ({ page }) => {
  await signIn(page, { email: 'viewer@crm.test', password: 'password' })
  await page.goto('/contacts')

  await expect(page.getByRole('heading', { name: 'Contacts' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'New contact' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Audit log' })).toHaveCount(0)
})

test('the assistant stays hidden until it is enabled', async ({ page }) => {
  await signIn(page)

  const status = await page.evaluate(async () => {
    const res = await fetch('/api/assistant/status', { headers: { Accept: 'application/json' } })
    return (await res.json()).data
  })

  if (status.enabled) {
    await expect(page.getByRole('button', { name: /Ask CRM/ })).toBeVisible()
  } else {
    await expect(page.getByRole('button', { name: /Ask CRM/ })).toHaveCount(0)
  }
})
