import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import App from '../src/App';

describe('App', () => {
  it('renders title', () => {
    render(<App />);
    expect(screen.getByText('UNED Activities Finder')).toBeInTheDocument();
  });

  it('renders welcome message', () => {
    render(<App />);
    expect(
      screen.getByText('Welcome to UNED Activities Finder')
    ).toBeInTheDocument();
  });

  it('renders language switcher', () => {
    render(<App />);
    expect(screen.getByLabelText('Language:')).toBeInTheDocument();
  });
});
