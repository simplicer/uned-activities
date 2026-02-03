import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import App from '../src/App';

describe('App', () => {
  it('renders header brand', () => {
    render(<App />);
    expect(
      screen.getByText('Universidad Nacional de Educación a Distancia')
    ).toBeInTheDocument();
  });

  it('renders language switcher', () => {
    render(<App />);
    expect(screen.getByRole('combobox')).toBeInTheDocument();
    expect(screen.getByText('Español')).toBeInTheDocument();
  });
});
