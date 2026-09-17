import { afterEach } from 'vitest'
import { cleanup } from '@testing-library/react'
import '@testing-library/jest-dom/vitest'

// Sin esto, lo que monta un caso queda en el documento para el siguiente.
afterEach(cleanup)
