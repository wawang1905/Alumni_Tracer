#!/usr/bin/env python3
"""
Alumni Charts - Python implementation of the JavaScript alumni graph functionality
Transposed from the original JavaScript code in StaffCommon.js
"""

import matplotlib.pyplot as plt
import matplotlib.patches as patches
import numpy as np
import pandas as pd
from typing import List, Dict, Any, Optional
import seaborn as sns
from collections import Counter
import warnings
warnings.filterwarnings('ignore')

# Set style to match the original design
plt.style.use('seaborn-v0_8-whitegrid')
sns.set_palette(['#1e4a76', '#3f6ea1', '#5f8fbe', '#7ba6cd', '#f59e0b', '#ef4444', '#10b981', '#8b5cf6'])

class AlumniCharts:
    """Python implementation of alumni chart functionality"""
    
    def __init__(self, alumni_data: List[Dict[str, Any]]):
        """
        Initialize with alumni data
        
        Args:
            alumni_data: List of dictionaries containing alumni information
        """
        self.alumni_data = alumni_data
        self.chart_colors = ['#1e4a76', '#3f6ea1', '#5f8fbe', '#7ba6cd', '#f59e0b', '#ef4444', '#10b981', '#8b5cf6']
        
    def render_alumni_status_chart(self, save_path: Optional[str] = None, show: bool = True):
        """
        Create a donut chart showing alumni status distribution
        Equivalent to renderAlumniStatusChart() in JavaScript
        """
        if not self.alumni_data:
            print("No alumni records yet.")
            return None
            
        # Count alumni by status
        status_counts = Counter()
        for alumni in self.alumni_data:
            status = str(alumni.get('status', '')).lower()
            if status in ['active', 'pending', 'inactive']:
                status_counts[status] += 1
            else:
                status_counts['inactive'] += 1  # Default for unknown status
        
        # Prepare data with colors matching JavaScript
        segments = [
            {'label': 'Active', 'count': status_counts.get('active', 0), 'color': '#1e4a76'},
            {'label': 'Pending', 'count': status_counts.get('pending', 0), 'color': '#f59e0b'},
            {'label': 'Inactive', 'count': status_counts.get('inactive', 0), 'color': '#94a3b8'}
        ]
        
        # Filter out zero-count segments
        segments = [seg for seg in segments if seg['count'] > 0]
        
        if not segments:
            print("No valid status data found.")
            return None
            
        # Create figure
        fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(12, 6))
        fig.patch.set_facecolor('white')
        
        # Donut chart
        sizes = [seg['count'] for seg in segments]
        colors = [seg['color'] for seg in segments]
        labels = [seg['label'] for seg in segments]
        
        wedges, texts, autotexts = ax1.pie(sizes, colors=colors, labels=None, autopct='', 
                                          startangle=90, wedgeprops=dict(width=0.58))
        
        # Add center text
        total = sum(sizes)
        ax1.text(0, 0, f'{total}', fontsize=28, fontweight='bold', ha='center', va='center', color='#16354d')
        ax1.text(0, -0.15, 'Alumni', fontsize=12, ha='center', va='center', color='#16354d', style='italic')
        
        # Legend with percentages
        legend_data = []
        for i, seg in enumerate(segments):
            percentage = round((seg['count'] / total) * 100) if total > 0 else 0
            legend_data.append({
                'label': seg['label'],
                'count': seg['count'],
                'percentage': percentage,
                'color': seg['color']
            })
        
        # Create legend as a table
        legend_text = []
        for item in legend_data:
            legend_text.append(f"{item['label']}: {item['percentage']}% ({item['count']} alumni)")
        
        ax2.axis('off')
        ax2.text(0, 0.9, 'Status Distribution', fontsize=14, fontweight='bold', ha='center', va='top')
        
        y_pos = 0.7
        for i, item in enumerate(legend_data):
            # Color box
            rect = patches.Rectangle((0.1, y_pos - 0.05), 0.1, 0.08, 
                                   facecolor=item['color'], edgecolor='none')
            ax2.add_patch(rect)
            
            # Text
            ax2.text(0.25, y_pos, f"{item['label']}", fontsize=12, fontweight='bold', va='center')
            ax2.text(0.25, y_pos - 0.03, f"{item['percentage']}% of alumni", fontsize=10, color='#6a7b8c', va='center')
            ax2.text(0.25, y_pos - 0.06, f"{item['count']}", fontsize=11, va='center')
            
            y_pos -= 0.15
        
        ax1.set_title('Alumni Status Overview', fontsize=16, fontweight='bold', pad=20)
        plt.tight_layout()
        
        if save_path:
            plt.savefig(save_path, dpi=300, bbox_inches='tight', facecolor='white')
        
        if show:
            plt.show()
        
        return fig
    
    def render_year_distribution(self, save_path: Optional[str] = None, show: bool = True):
        """
        Create a horizontal bar chart showing alumni distribution by graduation year
        Equivalent to renderYearDistribution() in JavaScript
        """
        if not self.alumni_data:
            print("No alumni records yet.")
            return None
            
        # Count alumni by graduation year
        year_counts = Counter()
        for alumni in self.alumni_data:
            year = str(alumni.get('graduationYear', '')).strip() or 'Unassigned'
            year_counts[year] += 1
        
        # Sort by year (descending) and take top 8
        sorted_years = sorted(year_counts.items(), 
                            key=lambda x: (x[0] != 'Unassigned', int(x[0]) if x[0].isdigit() else 0), 
                            reverse=True)[:8]
        
        if not sorted_years:
            print("No graduation year data found.")
            return None
            
        years = [f"Class of {year}" for year, count in sorted_years]
        counts = [count for year, count in sorted_years]
        
        # Create horizontal bar chart
        fig, ax = plt.subplots(figsize=(10, 6))
        fig.patch.set_facecolor('white')
        
        # Create bars with gradient effect
        bars = ax.barh(years, counts, color='#1e4a76', alpha=0.8)
        
        # Add gradient effect
        for bar in bars:
            bar.set_alpha(0.8)
        
        # Customize appearance
        ax.set_xlabel('Number of Alumni', fontsize=12, fontweight='bold')
        ax.set_title('Alumni by Graduation Year', fontsize=16, fontweight='bold', pad=20)
        
        # Add value labels on bars
        for i, (year, count) in enumerate(sorted_years):
            ax.text(count + max(counts) * 0.01, i, f'{count} alumni', 
                   va='center', fontsize=10, fontweight='bold')
        
        # Remove top and right spines
        ax.spines['top'].set_visible(False)
        ax.spines['right'].set_visible(False)
        
        plt.tight_layout()
        
        if save_path:
            plt.savefig(save_path, dpi=300, bbox_inches='tight', facecolor='white')
        
        if show:
            plt.show()
        
        return fig
    
    def render_college_distribution(self, save_path: Optional[str] = None, show: bool = True, 
                                  batch_filter: str = 'all'):
        """
        Create a pie chart showing alumni distribution by college
        Equivalent to renderCollegeDistributionChart() in JavaScript
        """
        filtered_data = self._filter_by_batch(batch_filter)
        
        if not filtered_data:
            print(f"No alumni records found for batch filter: {batch_filter}")
            return None
            
        # Count alumni by college
        college_counts = Counter()
        for alumni in filtered_data:
            college = str(alumni.get('college', '')).strip() or 'Unassigned'
            college_counts[college] += 1
        
        # Sort by count (descending)
        sorted_colleges = sorted(college_counts.items(), key=lambda x: x[1], reverse=True)
        
        if not sorted_colleges:
            print("No college data found.")
            return None
            
        colleges = [college for college, count in sorted_colleges]
        counts = [count for college, count in sorted_colleges]
        
        # Create pie chart
        fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(12, 6))
        fig.patch.set_facecolor('white')
        
        # Pie chart
        colors = self.chart_colors[:len(colleges)]
        wedges, texts, autotexts = ax1.pie(counts, colors=colors, labels=None, autopct='',
                                          startangle=90)
        
        # Add center text
        total = sum(counts)
        ax1.text(0, 0, f'{total}', fontsize=28, fontweight='bold', ha='center', va='center', color='#16354d')
        ax1.text(0, -0.15, 'Alumni', fontsize=12, ha='center', va='center', color='#16354d', style='italic')
        
        # Legend
        ax2.axis('off')
        ax2.text(0, 0.9, 'College Distribution', fontsize=14, fontweight='bold', ha='center', va='top')
        ax2.text(0, 0.8, f'Showing {total} alumni record(s).', fontsize=10, color='#6a7b8c', ha='center', va='top')
        
        y_pos = 0.6
        for i, (college, count) in enumerate(sorted_colleges):
            # Color box
            rect = patches.Rectangle((0.1, y_pos - 0.05), 0.1, 0.08, 
                                   facecolor=colors[i], edgecolor='none')
            ax2.add_patch(rect)
            
            # Text
            ax2.text(0.25, y_pos, f"{college}", fontsize=12, fontweight='bold', va='center')
            ax2.text(0.25, y_pos - 0.03, f"{count} alumni", fontsize=10, color='#6a7b8c', va='center')
            
            y_pos -= 0.12
        
        ax1.set_title('Alumni by College', fontsize=16, fontweight='bold', pad=20)
        plt.tight_layout()
        
        if save_path:
            plt.savefig(save_path, dpi=300, bbox_inches='tight', facecolor='white')
        
        if show:
            plt.show()
        
        return fig
    
    def render_course_distribution(self, save_path: Optional[str] = None, show: bool = True,
                                 batch_filter: str = 'all', college_filter: str = 'all'):
        """
        Create a pie chart showing alumni distribution by course
        Equivalent to renderCourseDistributionChart() in JavaScript
        """
        filtered_data = self._filter_by_batch_and_college(batch_filter, college_filter)
        
        if not filtered_data:
            print(f"No alumni records found for filters - Batch: {batch_filter}, College: {college_filter}")
            return None
            
        # Count alumni by course
        course_counts = Counter()
        for alumni in filtered_data:
            course = str(alumni.get('course', '')).strip() or 'Unassigned'
            course_counts[course] += 1
        
        # Sort by count (descending)
        sorted_courses = sorted(course_counts.items(), key=lambda x: x[1], reverse=True)
        
        if not sorted_courses:
            print("No course data found.")
            return None
            
        courses = [course for course, count in sorted_courses]
        counts = [count for course, count in sorted_courses]
        
        # Create pie chart
        fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(12, 6))
        fig.patch.set_facecolor('white')
        
        # Pie chart
        colors = self.chart_colors[:len(courses)]
        wedges, texts, autotexts = ax1.pie(counts, colors=colors, labels=None, autopct='',
                                          startangle=90)
        
        # Add center text
        total = sum(counts)
        ax1.text(0, 0, f'{total}', fontsize=28, fontweight='bold', ha='center', va='center', color='#16354d')
        ax1.text(0, -0.15, 'Alumni', fontsize=12, ha='center', va='center', color='#16354d', style='italic')
        
        # Legend
        ax2.axis('off')
        ax2.text(0, 0.9, 'Course Distribution', fontsize=14, fontweight='bold', ha='center', va='top')
        ax2.text(0, 0.8, f'Showing {total} alumni record(s).', fontsize=10, color='#6a7b8c', ha='center', va='top')
        
        y_pos = 0.6
        for i, (course, count) in enumerate(sorted_courses):
            # Color box
            rect = patches.Rectangle((0.1, y_pos - 0.05), 0.1, 0.08, 
                                   facecolor=colors[i], edgecolor='none')
            ax2.add_patch(rect)
            
            # Text
            ax2.text(0.25, y_pos, f"{course}", fontsize=12, fontweight='bold', va='center')
            ax2.text(0.25, y_pos - 0.03, f"{count} alumni", fontsize=10, color='#6a7b8c', va='center')
            
            y_pos -= 0.12
        
        ax1.set_title('Alumni by Course', fontsize=16, fontweight='bold', pad=20)
        plt.tight_layout()
        
        if save_path:
            plt.savefig(save_path, dpi=300, bbox_inches='tight', facecolor='white')
        
        if show:
            plt.show()
        
        return fig
    
    def _filter_by_batch(self, batch_filter: str) -> List[Dict[str, Any]]:
        """Filter alumni data by graduation year batch"""
        if batch_filter == 'all':
            return self.alumni_data
        
        return [alumni for alumni in self.alumni_data 
                if str(alumni.get('graduationYear', '')).strip() == batch_filter]
    
    def _filter_by_batch_and_college(self, batch_filter: str, college_filter: str) -> List[Dict[str, Any]]:
        """Filter alumni data by batch and college"""
        filtered = self._filter_by_batch(batch_filter)
        
        if college_filter == 'all':
            return filtered
        
        return [alumni for alumni in filtered 
                if str(alumni.get('college', '')).strip() == college_filter]
    
    def generate_all_charts(self, output_dir: str = 'charts'):
        """Generate all charts and save them to the specified directory"""
        import os
        os.makedirs(output_dir, exist_ok=True)
        
        charts_generated = []
        
        # Alumni Status Chart
        try:
            self.render_alumni_status_chart(
                save_path=os.path.join(output_dir, 'alumni_status_chart.png'),
                show=False
            )
            charts_generated.append('alumni_status_chart.png')
        except Exception as e:
            print(f"Error generating alumni status chart: {e}")
        
        # Year Distribution Chart
        try:
            self.render_year_distribution(
                save_path=os.path.join(output_dir, 'year_distribution_chart.png'),
                show=False
            )
            charts_generated.append('year_distribution_chart.png')
        except Exception as e:
            print(f"Error generating year distribution chart: {e}")
        
        # College Distribution Chart
        try:
            self.render_college_distribution(
                save_path=os.path.join(output_dir, 'college_distribution_chart.png'),
                show=False
            )
            charts_generated.append('college_distribution_chart.png')
        except Exception as e:
            print(f"Error generating college distribution chart: {e}")
        
        # Course Distribution Chart
        try:
            self.render_course_distribution(
                save_path=os.path.join(output_dir, 'course_distribution_chart.png'),
                show=False
            )
            charts_generated.append('course_distribution_chart.png')
        except Exception as e:
            print(f"Error generating course distribution chart: {e}")
        
        print(f"Charts generated: {charts_generated}")
        return charts_generated


# Sample data for testing
def get_sample_alumni_data():
    """Generate sample alumni data for testing"""
    return [
        {'id': 1, 'firstName': 'John', 'lastName': 'Doe', 'email': 'john@example.com', 
         'graduationYear': '2018', 'status': 'active', 'college': 'Engineering', 'course': 'Computer Science'},
        {'id': 2, 'firstName': 'Jane', 'lastName': 'Smith', 'email': 'jane@example.com', 
         'graduationYear': '2019', 'status': 'active', 'college': 'Business', 'course': 'Marketing'},
        {'id': 3, 'firstName': 'Mike', 'lastName': 'Johnson', 'email': 'mike@example.com', 
         'graduationYear': '2020', 'status': 'pending', 'college': 'Engineering', 'course': 'Electrical Engineering'},
        {'id': 4, 'firstName': 'Sarah', 'lastName': 'Williams', 'email': 'sarah@example.com', 
         'graduationYear': '2018', 'status': 'active', 'college': 'Arts', 'course': 'Fine Arts'},
        {'id': 5, 'firstName': 'David', 'lastName': 'Brown', 'email': 'david@example.com', 
         'graduationYear': '2021', 'status': 'inactive', 'college': 'Engineering', 'course': 'Computer Science'},
        {'id': 6, 'firstName': 'Emily', 'lastName': 'Davis', 'email': 'emily@example.com', 
         'graduationYear': '2019', 'status': 'active', 'college': 'Business', 'course': 'Finance'},
        {'id': 7, 'firstName': 'Robert', 'lastName': 'Miller', 'email': 'robert@example.com', 
         'graduationYear': '2020', 'status': 'active', 'college': 'Science', 'course': 'Biology'},
        {'id': 8, 'firstName': 'Lisa', 'lastName': 'Wilson', 'email': 'lisa@example.com', 
         'graduationYear': '2018', 'status': 'pending', 'college': 'Engineering', 'course': 'Mechanical Engineering'},
        {'id': 9, 'firstName': 'James', 'lastName': 'Moore', 'email': 'james@example.com', 
         'graduationYear': '2021', 'status': 'active', 'college': 'Arts', 'course': 'Music'},
        {'id': 10, 'firstName': 'Patricia', 'lastName': 'Taylor', 'email': 'patricia@example.com', 
         'graduationYear': '2019', 'status': 'active', 'college': 'Science', 'course': 'Chemistry'},
    ]


if __name__ == "__main__":
    # Example usage
    print("Alumni Charts - Python Implementation")
    print("=" * 50)
    
    # Load sample data
    alumni_data = get_sample_alumni_data()
    print(f"Loaded {len(alumni_data)} alumni records")
    
    # Create chart generator
    chart_generator = AlumniCharts(alumni_data)
    
    # Generate all charts
    charts = chart_generator.generate_all_charts()
    
    print(f"\nGenerated {len(charts)} charts:")
    for chart in charts:
        print(f"  - {chart}")
    
    # Show individual charts (commented out to avoid blocking)
    # chart_generator.render_alumni_status_chart()
    # chart_generator.render_year_distribution()
    # chart_generator.render_college_distribution()
    # chart_generator.render_course_distribution()
