import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import App from '../src/App';

describe('App', () => {
  it('renders header brand', () => {
    render(<App />);
    expect(screen.getByText('LEXEMAS')).toBeInTheDocument();
  });

  it('renders language switcher', () => {
    render(<App />);
    expect(screen.getAllByRole('combobox').length).toBeGreaterThan(0);
    expect(screen.getByText('Castellano')).toBeInTheDocument();
  });
});
